<?php
class SMD_Subdomain_Handler {
    public function __construct() {
        add_shortcode('member_nama', [$this, 'name_shortcode']);
        add_shortcode('member_phone', [$this, 'phone_shortcode']);
        add_shortcode('member_city', [$this, 'city_shortcode']);
        add_shortcode('member_address', [$this, 'address_shortcode']);
        add_shortcode('member_wa', [$this, 'whatsapp_shortcode']);

        // Add filters for Elementor and general content
        add_filter('elementor/frontend/the_content', [$this, 'process_custom_tags']);
        add_filter('widget_text', [$this, 'process_custom_tags']);
        add_filter('the_content', [$this, 'process_custom_tags']);

        // Tambahkan shortcode khusus untuk WhatsApp
        add_shortcode('member_wa', [$this, 'whatsapp_shortcode']);

        // Add content filters untuk menangani kedua format
        add_filter('the_content', [$this, 'process_member_tags'], 1);
        add_filter('widget_text', [$this, 'process_member_tags'], 1);
        add_filter('elementor/frontend/the_content', [$this, 'process_member_tags'], 1);
    }

    public function get_current_subdomain() {
        $host = strtolower($_SERVER['HTTP_HOST']);
        $parts = explode('.', $host);
        
        // Jika memiliki subdomain (parts lebih dari 2)
        if (count($parts) > 2) {
            return $parts[0];
        }
        
        // Fallback ke parameter GET jika tidak ada subdomain
        return isset($_GET['member']) ? sanitize_text_field($_GET['member']) : '';
    }

    private function get_user_by_subdomain($subdomain) {
        global $wpdb;
        
        // Langsung cari berdasarkan custom username
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'custom_username' 
            AND meta_value = %s",
            $subdomain
        ));

        return $user_id ? get_user_by('id', $user_id) : false;
    }

    private function get_member_data($field) {
        $subdomain = $this->get_current_subdomain();
        if (empty($subdomain)) return '';
        
        $user = $this->get_user_by_subdomain($subdomain);
        if ($user) {
            $value = get_user_meta($user->ID, $field, true);
            return !empty($value) ? esc_html($value) : '';
        }
        
        return '';
    }

    public function name_shortcode() {
        $subdomain = $this->get_current_subdomain();
        if (empty($subdomain)) return '';
        
        $user = $this->get_user_by_subdomain($subdomain);
        return $user ? esc_html($user->display_name) : '';
    }

    public function phone_shortcode($atts = []) {
        $args = shortcode_atts([
            'raw' => false
        ], $atts);
        
        $phone = $this->get_member_data('phone');
        
        if ($args['raw']) {
            return preg_replace('/[^0-9]/', '', $phone);
        }
        
        return $phone;
    }

    public function city_shortcode() {
        return $this->get_member_data('city');
    }

    public function address_shortcode() {
        return $this->get_member_data('address');
    }

    public function process_custom_tags($content) {
        if (empty($content)) return $content;

        // Handle WhatsApp URLs
        $content = str_replace('[member_wa]', $this->whatsapp_shortcode(), $content);
        
        // Handle other member tags
        $content = str_replace('member_nama', do_shortcode('[member_nama]'), $content);
        
        return $content;
    }

    public function whatsapp_shortcode() {
        $phone = $this->get_member_data('phone');
        if (empty($phone)) return '#';
        $clean_number = preg_replace('/[^0-9]/', '', $phone);
        return 'https://wa.me/' . $clean_number;
    }

    public function process_member_tags($content) {
        if (empty($content)) return $content;

        // Array of tag mappings (plain text => shortcode)
        $tag_mappings = [
            'member_nama' => '[member_nama]',
            'member_phone' => '[member_phone]',
            'member_city' => '[member_city]',
            'member_address' => '[member_address]',
            'member_wa' => '[member_wa]'
        ];

        // Replace plain text dengan shortcodes
        foreach ($tag_mappings as $plain => $shortcode) {
            // Avoid replacing if it's already a shortcode
            $content = preg_replace('/(?<!\[)' . preg_quote($plain, '/') . '(?!\])/', $shortcode, $content);
        }

        // Process shortcodes
        return do_shortcode($content);
    }
}