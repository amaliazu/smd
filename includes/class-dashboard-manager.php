<?php
class SMD_Dashboard_Manager {
    public function __construct() {
        add_shortcode('member_dashboard', [$this, 'dashboard_shortcode']);
    }

    public function dashboard_shortcode() {
        if (!is_user_logged_in()) {
            return 'Please login to access your dashboard.';
        }

        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $message = '';

        if (isset($_POST['update_profile'])) {
            $message = $this->handle_profile_update($user_id);
        }

        // Get all user meta data
        $profile_pic = get_user_meta($user_id, 'profile_pic', true);
        $custom_username = get_user_meta($user_id, 'custom_username', true);
        $phone = get_user_meta($user_id, 'phone', true);
        $city = get_user_meta($user_id, 'city', true);
        $web_url = get_user_meta($user_id, 'web_url', true);
        $registration_date = get_user_meta($user_id, 'registration_date', true);
        $approval_date = get_user_meta($user_id, 'approval_date', true);
        $formatted_reg_date = $registration_date ? date('d M Y H:i', strtotime($registration_date)) : '-';
        $formatted_approval_date = $approval_date ? date('d M Y H:i', strtotime($approval_date)) : '-';

        ob_start();
        ?>
        <div class="member-dashboard">
            <?php if ($message): ?>
                <div class="notice"><?php echo esc_html($message); ?></div>
            <?php endif; ?>

            <div class="member-info">
                <p><strong>Registration Date:</strong> <?php echo esc_html($formatted_reg_date); ?></p>
                <?php if ($approval_date): ?>
                    <p><strong>Approval Date:</strong> <?php echo esc_html($formatted_approval_date); ?></p>
                <?php endif; ?>
            </div>

            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Profile Picture</label>
                    <?php if ($profile_pic): ?>
                        <div class="current-profile-pic">
                            <img src="<?php echo esc_url($profile_pic); ?>" style="max-width:150px;"><br>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="profile_pic" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="display_name" value="<?php echo esc_attr($current_user->display_name); ?>" required>
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?php echo esc_attr($current_user->user_email); ?>" required>
                </div>

                <div class="form-group">
                    <label>Username/Web *</label>
                    <input type="text" name="custom_username" value="<?php echo esc_attr($custom_username); ?>" required>
                    <p class="description">This will be your subdomain URL</p>
                </div>

                <div class="form-group">
                    <label>WhatsApp Number *</label>
                    <input type="text" name="phone" value="<?php echo esc_attr($phone); ?>" required>
                    <p class="description">Format: 628xxxxxxxxxx (Example: 6287829071652)</p>
                </div>

                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" value="<?php echo esc_attr($city); ?>">
                </div>

                <div class="form-group">
                    <label>Web URL</label>
                    <input type="url" name="web_url" value="<?php echo esc_attr($web_url); ?>">
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password">
                    <p class="description">Leave empty to keep current password</p>
                </div>

                <button type="submit" name="update_profile" class="button button-primary">Update Profile</button>
            </form>

            <style>
            .member-dashboard {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .form-group {
                margin-bottom: 15px;
            }
            .form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
            .form-group input[type="text"],
            .form-group input[type="email"],
            .form-group input[type="url"],
            .form-group input[type="password"] {
                width: 100%;
                padding: 8px;
            }
            .description {
                font-size: 0.9em;
                color: #666;
                margin-top: 5px;
            }
            .notice {
                padding: 10px;
                margin-bottom: 20px;
                background: #fff;
                border-left: 4px solid #46b450;
            }
            .current-profile-pic {
                margin-bottom: 10px;
            }
            </style>
        </div>
        <?php
        return ob_get_clean();
    }

    private function handle_profile_update($user_id) {
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
                update_user_meta($user_id, 'profile_pic', $movefile['url']);
            } else {
                return 'Error uploading profile picture.';
            }
        }

        $userdata = [
            'ID' => $user_id,
            'display_name' => sanitize_text_field($_POST['display_name']),
            'user_email' => sanitize_email($_POST['email'])
        ];

        // Check email uniqueness
        if (email_exists($_POST['email']) && email_exists($_POST['email']) != $user_id) {
            return 'Email already in use by another member.';
        }

        // Handle password update
        if (!empty($_POST['password'])) {
            $userdata['user_pass'] = $_POST['password'];
        }

        // Update user data
        $result = wp_update_user($userdata);

        if (is_wp_error($result)) {
            return 'Error updating profile.';
        }

        // Handle profile picture
        if (!empty($_FILES['profile_pic']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $upload = wp_handle_upload($_FILES['profile_pic'], ['test_form' => false]);
            
            if (!empty($upload['url'])) {
                update_user_meta($user_id, 'profile_pic', $upload['url']);
            }
        }

        // Update custom username
        $custom_username = sanitize_text_field($_POST['custom_username']);
        $username_check = $this->is_custom_username_available($custom_username, $user_id);
        if (!$username_check) {
            return 'Username already taken.';
        }
        update_user_meta($user_id, 'custom_username', $custom_username);

        // Update other meta fields
        update_user_meta($user_id, 'web_url', esc_url_raw($_POST['web_url']));
        update_user_meta($user_id, 'phone', sanitize_text_field($_POST['phone']));
        update_user_meta($user_id, 'city', sanitize_text_field($_POST['city']));

        return 'Profile updated successfully!';
    }

    private function is_custom_username_available($username, $exclude_user_id = 0) {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'custom_username' 
            AND meta_value = %s 
            AND user_id != %d",
            $username,
            $exclude_user_id
        ));
        return empty($exists);
    }
}