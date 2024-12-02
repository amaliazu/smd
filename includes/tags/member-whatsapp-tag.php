<?php

class SMD_Member_WhatsApp_Tag extends \Elementor\Core\DynamicTags\Tag {
    public function get_name() {
        return 'member-whatsapp';
    }

    public function get_title() {
        return 'Member WhatsApp';
    }

    public function get_group() {
        return 'site';
    }

    public function get_categories() {
        return ['url'];
    }

    public function render() {
        // Get current subdomain user's WhatsApp
        $subdomain = get_current_subdomain();
        if (empty($subdomain)) return;
        
        global $wpdb;
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'custom_username' 
            AND meta_value = %s",
            $subdomain
        ));

        if ($user_id) {
            $phone = get_user_meta($user_id, 'phone', true);
            $clean_number = preg_replace('/[^0-9]/', '', $phone);
            echo 'https://wa.me/' . $clean_number;
        }
    }
}
