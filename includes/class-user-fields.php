<?php
class SMD_User_Fields {
    public function __construct() {
        add_action('show_user_profile', [$this, 'add_custom_user_fields']);
        add_action('edit_user_profile', [$this, 'add_custom_user_fields']);
        add_action('personal_options_update', [$this, 'save_custom_user_fields']);
        add_action('edit_user_profile_update', [$this, 'save_custom_user_fields']);
        add_action('user_edit_form_tag', [$this, 'add_form_enctype']);
    }

    public function add_custom_user_fields($user) {
        $profile_pic = get_user_meta($user->ID, 'profile_pic', true);
        $custom_username = get_user_meta($user->ID, 'custom_username', true);
        $web_url = get_user_meta($user->ID, 'web_url', true);
        ?>
        <h3>Additional Information</h3>
        <table class="form-table">
            <tr>
                <th><label for="profile_pic">Profile Picture</label></th>
                <td>
                    <?php if ($profile_pic): ?>
                        <img src="<?php echo esc_url($profile_pic); ?>" style="max-width:150px;"><br>
                    <?php endif; ?>
                    <input type="file" name="profile_pic" accept="image/*">
                </td>
            </tr>
            <tr>
                <th><label for="custom_username">Username/Web</label></th>
                <td>
                    <input type="text" name="custom_username" value="<?php echo esc_attr($custom_username); ?>">
                    <p class="description">This will be used for your subdomain URL</p>
                </td>
            </tr>
            <tr>
                <th><label for="web_url">URL Web</label></th>
                <td>
                    <input type="text" name="web_url" value="<?php echo esc_attr($web_url); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="phone">WhatsApp Number</label></th>
                <td>
                    <input type="text" name="phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'phone', true)); ?>">
                    <p class="description">Format: 628xxxxxxxxxx (Example: 6287829071652)</p>
                </td>
            </tr>
            <tr>
                <th><label for="city">City</label></th>
                <td>
                    <input type="text" name="city" value="<?php echo esc_attr(get_user_meta($user->ID, 'city', true)); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="address">Address</label></th>
                <td>
                    <textarea name="address"><?php echo esc_attr(get_user_meta($user->ID, 'address', true)); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }

    public function add_form_enctype() {
        echo ' enctype="multipart/form-data"';
    }

    public function save_custom_user_fields($user_id) {
        // Handle profile picture upload
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
                error_log('Upload success: ' . print_r($movefile, true));
                update_user_meta($user_id, 'profile_pic', $movefile['url']);
            } else {
                error_log('Upload error: ' . print_r($_FILES['profile_pic'], true));
                if (isset($movefile['error'])) {
                    error_log('Move error: ' . $movefile['error']);
                }
            }
        }

        // Handle custom username
        if (!empty($_POST['custom_username'])) {
            $custom_username = sanitize_text_field($_POST['custom_username']);
            if ($this->is_custom_username_available($custom_username, $user_id)) {
                update_user_meta($user_id, 'custom_username', $custom_username);
            }
        }

        update_user_meta($user_id, 'web_url', esc_url_raw($_POST['web_url']));
        update_user_meta($user_id, 'phone', sanitize_text_field($_POST['phone']));
        update_user_meta($user_id, 'city', sanitize_text_field($_POST['city']));
        update_user_meta($user_id, 'address', sanitize_textarea_field($_POST['address']));
    }

    public function is_custom_username_available($username, $exclude_user_id = 0) {
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