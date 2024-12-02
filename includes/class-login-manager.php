<?php
class SMD_Login_Manager {
    public function __construct() {
        add_shortcode('member_login', [$this, 'login_shortcode']);
        add_action('init', [$this, 'handle_login']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_add_new_member', [$this, 'handle_new_member']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_update_member_status', [$this, 'update_member_status']);
        add_action('wp_ajax_delete_member', [$this, 'delete_member']);
        add_action('wp_ajax_get_member_data', [$this, 'get_member_data']);
        add_action('wp_ajax_update_member', [$this, 'update_member']);
        add_action('init', [$this, 'prevent_wp_login']);
        add_action('wp_ajax_check_field_availability', [$this, 'check_field_availability']);
        add_action('wp_ajax_nopriv_check_field_availability', [$this, 'check_field_availability']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function login_shortcode() {
        // Jangan tampilkan form jika sedang di admin
        if (is_admin()) {
            return '';
        }

        ob_start();
        
        // Display error messages
        if (isset($_GET['error'])) {
            $error_type = sanitize_text_field($_GET['error']);
            $error_message = $this->get_error_message($error_type);
            ?>
            <div class="login-error">
                <?php echo esc_html($error_message); ?>
            </div>
            <style>
            .login-error {
                padding: 10px 15px;
                margin-bottom: 20px;
                border-left: 4px solid #dc3232;
                background: #fff;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            </style>
            <?php
        }

        // Display login form
        ?>
        <form method="post" action="">
            <p>
                <label>Username/Web/Email</label>
                <input type="text" name="log" required value="<?php echo isset($_POST['log']) ? esc_attr($_POST['log']) : ''; ?>">
            </p>
            <p>
                <label>Password</label>
                <input type="password" name="pwd" required>
            </p>
            <p>
                <input type="submit" name="member_login" value="Login">
            </p>
        </form>
        <?php

        return ob_get_clean();
    }

    private function get_error_message($error_type) {
        $messages = [
            'invalid_login' => 'Username or password is incorrect.',
            'empty_fields' => 'Please fill in all required fields.',
            'pending' => 'Your account is pending approval.',
            'not_member' => 'This login is only for members.',
            'inactive' => 'Your account has been deactivated.'
        ];

        return isset($messages[$error_type]) ? $messages[$error_type] : 'An unknown error occurred.';
    }

    public function handle_login() {
        if (!isset($_POST['member_login'])) return;

        // Validate required fields
        if (empty($_POST['log']) || empty($_POST['pwd'])) {
            $_GET['error'] = 'empty_fields';
            return;
        }

        $login = $_POST['log'];
        $password = $_POST['pwd'];
        
        // Cek apakah user bisa login ke WordPress
        $user = wp_authenticate($login, $password);
        
        if (!is_wp_error($user)) {
            // Check if user is administrator first
            if (in_array('administrator', $user->roles)) {
                wp_set_auth_cookie($user->ID, true);
                wp_redirect(admin_url());
                exit;
            }
            
            if (in_array('subscriber', $user->roles)) {
                $status = get_user_meta($user->ID, 'account_status', true);
                
                if ($status === 'pending') {
                    $_GET['error'] = 'pending';
                    return;
                } elseif ($status !== 'active') {
                    $_GET['error'] = 'inactive';
                    return;
                }
                
                wp_set_auth_cookie($user->ID, true);
                wp_redirect(home_url('/member-dashboard'));
                exit;
            }
        }

        // Login gagal, coba cek custom username
        $custom_user = $this->get_user_by_custom_username($login);
        if ($custom_user && wp_check_password($password, $custom_user->user_pass, $custom_user->ID)) {
            $status = get_user_meta($custom_user->ID, 'account_status', true);
            
            if ($status !== 'active') {
                $_GET['error'] = 'pending';
                return;
            }
            
            wp_set_auth_cookie($custom_user->ID, true);
            wp_redirect(home_url('/member-dashboard'));
            exit;
        }
        
        // Login gagal
        $_GET['error'] = 'invalid_login';
    }

    private function get_user_by_custom_username($username) {
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'custom_username' 
            AND meta_value = %s",
            $username
        ));

        if ($user_id) {
            return get_user_by('id', $user_id);
        }

        return false;
    }

    public function add_admin_menu() {
        add_menu_page(
            'Simple Member',
            'Simple Member',
            'manage_options',
            'simple-member',
            [$this, 'render_admin_page'],
            'dashicons-groups',
            30
        );
    }

    public function enqueue_admin_scripts($hook) {
        if('toplevel_page_simple-member' !== $hook) return;
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Simple Member Management
                <button class="page-title-action" onclick="jQuery('#add-member-modal').dialog('open')">Add New Member</button>
            </h1>

            <?php
            // Display messages
            if (isset($_GET['message'])) {
                $message = sanitize_text_field($_GET['message']);
                echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
            }
            ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Profile Picture</th>
                        <th>Full Name</th>
                        <th>Username/Web</th>
                        <th>Email</th>
                        <th>WhatsApp</th>
                        <th>City</th>
                        <th>Web URL</th>
                        <th>Registration Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = get_users(['role' => 'subscriber']);
                    foreach($users as $user) {
                        $status = get_user_meta($user->ID, 'account_status', true) ?: 'pending';
                        $profile_pic = get_user_meta($user->ID, 'profile_pic', true);
                        $custom_username = get_user_meta($user->ID, 'custom_username', true);
                        $phone = get_user_meta($user->ID, 'phone', true);
                        $city = get_user_meta($user->ID, 'city', true);
                        $web_url = get_user_meta($user->ID, 'web_url', true);
                        $registration_date = get_user_meta($user->ID, 'registration_date', true);
                        $formatted_date = $registration_date ? date('d M Y H:i', strtotime($registration_date)) : '-';
                        ?>
                        <tr>
                            <td>
                                <?php if ($profile_pic): ?>
                                    <img src="<?php echo esc_url($profile_pic); ?>" style="max-width:50px;">
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td><?php echo esc_html($custom_username); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html($phone); ?></td>
                            <td><?php echo esc_html($city); ?></td>
                            <td><?php echo $web_url ? '<a href="' . esc_url($web_url) . '" target="_blank">Visit Site</a>' : ''; ?></td>
                            <td><?php echo esc_html($formatted_date); ?></td>
                            <td><?php echo esc_html($status); ?></td>
                            <td>
                                <?php if($status === 'pending' || $status === 'inactive'): ?>
                                    <button class="button approve-member" data-userid="<?php echo $user->ID; ?>">
                                        <?php echo $status === 'pending' ? 'Approve' : 'Activate'; ?>
                                    </button>
                                <?php elseif($status === 'active'): ?>
                                    <button class="button button-secondary disable-member" data-userid="<?php echo $user->ID; ?>">Disable</button>
                                <?php endif; ?>
                                <button class="button edit-member" data-userid="<?php echo $user->ID; ?>">Edit</button>
                                <button class="button delete-member" data-userid="<?php echo $user->ID; ?>">Delete</button>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>

            <!-- Add Member Modal -->
            <div id="add-member-modal" title="Add New Member" style="display:none;">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="add-member-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_new_member">
                    <?php wp_nonce_field('add_new_member_nonce', 'member_nonce'); ?>
                    <p>
                        <label>Profile Picture</label><br>
                        <input type="file" name="profile_pic" accept="image/*" class="widefat">
                    </p>
                    <p>
                        <label>Full Name</label><br>
                        <input type="text" name="full_name" required class="widefat">
                    </p>
                    <p>
                        <label>Email</label><br>
                        <input type="email" name="email" required class="widefat">
                    </p>
                    <p>
                        <label>WhatsApp Number</label><br>
                        <input type="text" name="phone" class="widefat">
                        <span class="description">Format: 628xxxxxxxxxx</span>
                    </p>
                    <p>
                        <label>City</label><br>
                        <input type="text" name="city" class="widefat">
                    </p>
                    <p>
                        <label>Username/Web</label><br>
                        <input type="text" name="custom_username" required class="widefat">
                        <span class="description">For subdomain URL</span>
                    </p>
                    <p>
                        <label>Password</label><br>
                        <input type="password" name="password" required class="widefat">
                    </p>
                    <p>
                        <label>Web URL</label><br>
                        <input type="url" name="web_url" class="widefat">
                    </p>
                    <p>
                        <input type="submit" class="button button-primary" value="Add Member">
                    </p>
                </form>
            </div>

            <!-- Edit Member Modal -->
            <div id="edit-member-modal" title="Edit Member" style="display:none;">
                <form method="post" id="edit-member-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_member">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <?php wp_nonce_field('member_action_nonce', 'edit_nonce'); ?>
                    <div id="current_profile_pic"></div>
                    <p>
                        <label>Profile Picture</label><br>
                        <input type="file" name="profile_pic" accept="image/*" class="widefat">
                    </p>
                    <p>
                        <label>Full Name</label><br>
                        <input type="text" name="full_name" id="edit_full_name" required class="widefat">
                    </p>
                    <p>
                        <label>Email</label><br>
                        <input type="email" name="email" id="edit_email" required class="widefat">
                    </p>
                    <p>
                        <label>Username/Web</label><br>
                        <input type="text" name="custom_username" id="edit_custom_username" required class="widefat">
                    </p>
                    <p>
                        <label>WhatsApp Number</label><br>
                        <input type="text" name="phone" id="edit_phone" class="widefat">
                        <span class="description">Format: 628xxxxxxxxxx</span>
                    </p>
                    <p>
                        <label>City</label><br>
                        <input type="text" name="city" id="edit_city" class="widefat">
                    </p>
                    <p>
                        <label>Web URL</label><br>
                        <input type="url" name="web_url" id="edit_web_url" class="widefat">
                    </p>
                    <p>
                        <label>New Password</label><br>
                        <input type="password" name="password" class="widefat">
                        <span class="description">Leave empty to keep current password</span>
                    </p>
                    <p>
                        <input type="submit" class="button button-primary" value="Update Member">
                    </p>
                </form>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // Initialize modal
                $("#add-member-modal").dialog({
                    autoOpen: false,
                    modal: true,
                    width: 400,
                    close: function() {
                        $("#add-member-form")[0].reset();
                    }
                });

                // Handle approve/activate action
                $(".approve-member").click(function() {
                    var status = $(this).text().toLowerCase();
                    var confirmMsg = status === 'approve' ? 
                        'Are you sure you want to approve this member?' : 
                        'Are you sure you want to activate this member?';
                    
                    if(!confirm(confirmMsg)) return;
                    
                    var userId = $(this).data('userid');
                    $.post(ajaxurl, {
                        action: 'update_member_status',
                        user_id: userId,
                        status: 'active',
                        nonce: '<?php echo wp_create_nonce("member_action_nonce"); ?>'
                    }, function(response) {
                        if(response.success) {
                            location.reload();
                        }
                    });
                });

                // Handle disable action
                $(".disable-member").click(function() {
                    if(!confirm('Are you sure you want to disable this member?')) return;
                    
                    var userId = $(this).data('userid');
                    $.post(ajaxurl, {
                        action: 'update_member_status',
                        user_id: userId,
                        status: 'inactive',
                        nonce: '<?php echo wp_create_nonce("member_action_nonce"); ?>'
                    }, function(response) {
                        if(response.success) {
                            location.reload();
                        }
                    });
                });

                // Handle delete action
                $(".delete-member").click(function() {
                    if(!confirm('Are you sure you want to delete this member?')) return;
                    
                    var userId = $(this).data('userid');
                    $.post(ajaxurl, {
                        action: 'delete_member',
                        user_id: userId,
                        nonce: '<?php echo wp_create_nonce("member_action_nonce"); ?>'
                    }, function(response) {
                        if(response.success) {
                            location.reload();
                        }
                    });
                });

                // Initialize edit modal
                $("#edit-member-modal").dialog({
                    autoOpen: false,
                    modal: true,
                    width: 400,
                    close: function() {
                        $("#edit-member-form")[0].reset();
                    }
                });

                // Handle edit button click
                $(".edit-member").click(function() {
                    var userId = $(this).data('userid');
                    
                    // Get member data
                    $.post(ajaxurl, {
                        action: 'get_member_data',
                        user_id: userId,
                        nonce: '<?php echo wp_create_nonce("member_action_nonce"); ?>'
                    }, function(response) {
                        if(response.success) {
                            $('#edit_user_id').val(userId);
                            $('#edit_full_name').val(response.data.full_name);
                            $('#edit_email').val(response.data.email);
                            $('#edit_custom_username').val(response.data.custom_username);
                            $('#edit_phone').val(response.data.phone);
                            $('#edit_city').val(response.data.city);
                            $('#edit_web_url').val(response.data.web_url);
                            
                            // Show current profile picture if exists
                            if(response.data.profile_pic) {
                                $('#current_profile_pic').html('<img src="' + response.data.profile_pic + '" style="max-width:150px;"><br>');
                            }
                            
                            $("#edit-member-modal").dialog('open');
                        }
                    });
                });

                // Handle edit form submission
                $("#edit-member-form").on('submit', function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    formData.append('action', 'update_member');
                    formData.append('nonce', '<?php echo wp_create_nonce("member_action_nonce"); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if(response.success) {
                                $("#edit-member-modal").dialog('close');
                                location.reload();
                            } else {
                                alert('Error updating member');
                            }
                        },
                        error: function() {
                            alert('Error processing request');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    public function handle_new_member() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        check_admin_referer('add_new_member_nonce', 'member_nonce');

        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];

        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'role' => 'subscriber'
        );

        $user_id = wp_insert_user($user_data);

        if (!is_wp_error($user_id)) {
            update_user_meta($user_id, 'account_status', 'active');
            wp_redirect(admin_url('admin.php?page=simple-member&message=Member added successfully'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=simple-member&message=Error adding member'));
            exit;
        }
    }

    public function update_member_status() {
        check_ajax_referer('member_action_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        $user_id = intval($_POST['user_id']);
        $status = sanitize_text_field($_POST['status']);
        
        update_user_meta($user_id, 'account_status', $status);

        // Update approval date if status is being set to active
        if ($status === 'active') {
            update_user_meta($user_id, 'approval_date', current_time('mysql'));
        }

        wp_send_json_success();
    }

    public function delete_member() {
        check_ajax_referer('member_action_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        $user_id = intval($_POST['user_id']);
        require_once(ABSPATH.'wp-admin/includes/user.php');
        
        if (wp_delete_user($user_id)) {
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    public function get_member_data() {
        check_ajax_referer('member_action_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);

        if ($user) {
            wp_send_json_success([
                'full_name' => $user->display_name,
                'email' => $user->user_email,
                'custom_username' => get_user_meta($user_id, 'custom_username', true),
                'phone' => get_user_meta($user_id, 'phone', true),
                'city' => get_user_meta($user_id, 'city', true),
                'web_url' => get_user_meta($user_id, 'web_url', true),
                'profile_pic' => get_user_meta($user_id, 'profile_pic', true),
                'registration_date' => get_user_meta($user_id, 'registration_date', true),
                'approval_date' => get_user_meta($user_id, 'approval_date', true)
            ]);
        }
        wp_send_json_error();
    }

    public function update_member() {
        check_ajax_referer('member_action_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        $user_id = intval($_POST['user_id']);

        // Handle profile picture upload first
        if (!empty($_FILES['profile_pic']['name'])) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
            }

            $uploaded_file = $_FILES['profile_pic'];
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($uploaded_file, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                // Update profile picture URL in user meta
                update_user_meta($user_id, 'profile_pic', $movefile['url']);
            }
        }

        // Update user data
        $userdata = [
            'ID' => $user_id,
            'display_name' => sanitize_text_field($_POST['full_name']),
            'user_email' => sanitize_email($_POST['email'])
        ];

        if (!empty($_POST['password'])) {
            $userdata['user_pass'] = $_POST['password'];
        }

        $result = wp_update_user($userdata);

        if (!is_wp_error($result)) {
            update_user_meta($user_id, 'custom_username', sanitize_text_field($_POST['custom_username']));
            update_user_meta($user_id, 'phone', sanitize_text_field($_POST['phone']));
            update_user_meta($user_id, 'city', sanitize_text_field($_POST['city']));
            update_user_meta($user_id, 'web_url', esc_url_raw($_POST['web_url']));
            
            wp_send_json_success();
        }

        wp_send_json_error();
    }

    public function prevent_wp_login() {
        // Get current page URL
        $page_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        
        // Jangan redirect jika user sudah login atau mencoba mengakses wp-admin
        if (is_user_logged_in() || strpos($page_url, 'wp-admin') !== false) {
            return;
        }

        // Hanya redirect wp-login.php untuk non-admin
        if (strpos($page_url, 'wp-login.php') !== false) {
            // Allow WP core actions (like reset password)
            if (isset($_REQUEST['action'])) {
                return;
            }
            
            wp_redirect(home_url('/member-login'));
            exit();
        }
    }

    public function enqueue_scripts() {
        wp_localize_script('jquery', 'ajaxurl', admin_url('admin-ajax.php'));
    }

    public function check_field_availability() {
        check_ajax_referer('field_check_nonce', 'nonce');
        
        $field = sanitize_text_field($_POST['field']);
        $value = sanitize_text_field($_POST['value']);
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        $response = ['available' => false];

        switch($field) {
            case 'email':
                $exists = email_exists($value);
                $response['available'] = !$exists || $exists == $user_id;
                break;

            case 'custom_username':
                global $wpdb;
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT user_id FROM {$wpdb->usermeta} 
                    WHERE meta_key = 'custom_username' 
                    AND meta_value = %s 
                    AND user_id != %d",
                    $value,
                    $user_id
                ));
                $response['available'] = empty($exists);
                break;

            case 'phone':
                global $wpdb;
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT user_id FROM {$wpdb->usermeta} 
                    WHERE meta_key = 'phone' 
                    AND meta_value = %s 
                    AND user_id != %d",
                    $value,
                    $user_id
                ));
                $response['available'] = empty($exists);
                break;
        }

        wp_send_json($response);
    }
}