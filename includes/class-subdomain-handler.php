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
        
        // Cari berdasarkan custom username terlebih dahulu
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'custom_username' 
            AND meta_value = %s",
            $subdomain
        ));

        // Jika tidak ditemukan, cari berdasarkan user_login
        if (!$user_id) {
            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} 
                WHERE user_login = %s",
                $subdomain
            ));
            $user_id = $user ? $user->ID : false;
        }

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
        
        // Debugging
        error_log('Current Subdomain: ' . $subdomain);
        
        if (empty($subdomain)) {
            error_log('No subdomain detected');
            return 'No subdomain detected';
        }
        
        $user = $this->get_user_by_subdomain($subdomain);
        
        if (!$user) {
            error_log('No user found for subdomain: ' . $subdomain);
            return 'User not found';
        }

        // Debug user data
        error_log('User ID: ' . $user->ID);
        error_log('User Login: ' . $user->user_login);
        error_log('Display Name: ' . $user->display_name);
        
        // Ambil nama dari custom field jika ada
        $custom_name = get_user_meta($user->ID, 'full_name', true);
        if (!empty($custom_name)) {
            return esc_html($custom_name);
        }
        
        // Jika tidak ada custom name, gunakan display name
        if (!empty($user->display_name) && $user->display_name !== $user->user_login) {
            return esc_html($user->display_name);
        }
        
        // Coba gabungkan first name dan last name
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $last_name = get_user_meta($user->ID, 'last_name', true);
        if (!empty($first_name) || !empty($last_name)) {
            return esc_html(trim($first_name . ' ' . $last_name));
        }
        
        // Fallback ke user login jika tidak ada nama lain
        return esc_html($user->user_login);
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