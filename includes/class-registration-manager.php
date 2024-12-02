<?php
class SMD_Registration_Manager {
    public function __construct() {
        add_shortcode('member_register', [$this, 'registration_form']);
        add_action('init', [$this, 'handle_registration']);
    }

    public function registration_form() {
        if (is_user_logged_in()) {
            return 'You are already registered and logged in.';
        }

        $output = '
        <form method="post" class="member-registration-form" enctype="multipart/form-data">
            ' . wp_nonce_field('member_registration', '_wpnonce', true, false) . '
            <div class="form-group">
                <label>Profile Picture *</label>
                <input type="file" name="profile_pic" accept="image/*" required>
            </div>
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>WhatsApp Number *</label>
                <input type="text" name="phone" required>
                <p class="description">Format: 628xxxxxxxxxx (Example: 6287829071652)</p>
            </div>
            <div class="form-group">
                <label>City *</label>
                <input type="text" name="city" required>
            </div>
            <div class="form-group">
                <label>Username/Web *</label>
                <input type="text" name="custom_username" required>
                <p class="description">This will be your subdomain URL</p>
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Web URL</label>
                <input type="url" name="web_url">
            </div>
            <div class="form-group">
                <input type="submit" name="member_register" value="Register">
            </div>
        </form>';

        // Add validation script
        $output .= '
        <script>
        jQuery(document).ready(function($) {
            var timer;
            var submitButton = $("input[name=member_register]");
            var nonce = "' . wp_create_nonce("field_check_nonce") . '";

            function checkAvailability(field, value) {
                clearTimeout(timer);
                var feedback = field.next(".field-feedback");
                if (!feedback.length) {
                    feedback = $("<div class=\'field-feedback\'></div>").insertAfter(field);
                }

                timer = setTimeout(function() {
                    feedback.html("Checking...");
                    $.post(ajaxurl, {
                        action: "check_field_availability",
                        field: field.attr("name"),
                        value: value,
                        nonce: nonce
                    }, function(response) {
                        if (response.available) {
                            feedback.html("<span style=\'color:green\'>Available</span>");
                            field.data("valid", true);
                        } else {
                            feedback.html("<span style=\'color:red\'>Already taken</span>");
                            field.data("valid", false);
                        }
                        validateForm();
                    });
                }, 500);
            }

            function validateForm() {
                var isValid = true;
                $("input[data-validate]").each(function() {
                    if ($(this).data("valid") === false) {
                        isValid = false;
                        return false;
                    }
                });
                submitButton.prop("disabled", !isValid);
            }

            // Add validation to fields
            $("input[name=email], input[name=custom_username], input[name=phone]")
                .attr("data-validate", true)
                .data("valid", true)
                .on("keyup", function() {
                    checkAvailability($(this), $(this).val());
                });

            // Initial form submit disabled
            submitButton.prop("disabled", true);
        });
        </script>
        <style>
        .field-feedback {
            font-size: 12px;
            margin-top: 5px;
        }
        </style>';

        return $output;
    }

    public function handle_registration() {
        if (!isset($_POST['member_register'])) return;

        if (!wp_verify_nonce($_POST['_wpnonce'], 'member_registration')) {
            wp_die('Invalid nonce');
        }

        // Check email uniqueness
        if (email_exists($_POST['email'])) {
            wp_die('Email already registered');
        }

        // Check custom username availability
        if ($this->is_custom_username_taken($_POST['custom_username'])) {
            wp_die('Username already taken');
        }

        // Handle profile picture upload
        $profile_pic_url = '';
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
                $profile_pic_url = $movefile['url'];
            }
        }

        // Create user
        $user_id = wp_insert_user([
            'user_login' => sanitize_user($_POST['custom_username']),
            'user_email' => sanitize_email($_POST['email']),
            'user_pass' => $_POST['password'],
            'display_name' => sanitize_text_field($_POST['full_name']),
            'role' => 'subscriber'
        ]);

        if (!is_wp_error($user_id)) {
            // Update user meta
            update_user_meta($user_id, 'profile_pic', $profile_pic_url);
            update_user_meta($user_id, 'custom_username', sanitize_text_field($_POST['custom_username']));
            update_user_meta($user_id, 'web_url', esc_url_raw($_POST['web_url']));
            update_user_meta($user_id, 'phone', sanitize_text_field($_POST['phone']));
            update_user_meta($user_id, 'city', sanitize_text_field($_POST['city']));
            update_user_meta($user_id, 'account_status', 'pending');
            update_user_meta($user_id, 'registration_date', current_time('mysql'));

            wp_redirect(home_url('/registration-success'));
            exit;
        }
    }

    private function is_custom_username_taken($username) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'custom_username' 
            AND meta_value = %s",
            $username
        ));
    }
}