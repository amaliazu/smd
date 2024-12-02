<?php
/*
Plugin Name: Simple Member Dashboard
Description: Custom membership system with frontend management
Version: 2.0
Author: Ardi 12345
*/

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Define plugin constants
define('SMD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SMD_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load required files
require_once SMD_PLUGIN_DIR . 'includes/class-user-fields.php';
require_once SMD_PLUGIN_DIR . 'includes/class-login-manager.php';
require_once SMD_PLUGIN_DIR . 'includes/class-dashboard-manager.php';
require_once SMD_PLUGIN_DIR . 'includes/class-subdomain-handler.php';
require_once SMD_PLUGIN_DIR . 'includes/class-registration-manager.php';

// Initialize classes
new SMD_User_Fields();
new SMD_Login_Manager();  // This already has the login shortcode
new SMD_Dashboard_Manager();  // This already has the dashboard shortcode
new SMD_Subdomain_Handler();
new SMD_Registration_Manager();

// Function to get current subdomain
function get_current_subdomain() {
    $host = strtolower($_SERVER['HTTP_HOST']);
    
    // Debug: tambahkan ini sementara untuk melihat nilai host
    error_log('Current host: ' . $host);
    
    // Handle localhost cases
    if (strpos($host, 'kpglocal.test') !== false) {
        $parts = explode('.', $host);
        
        // Jika ada subdomain (contoh: admin72.kpglocal.test)
        if (count($parts) > 2) {
            error_log('Subdomain found: ' . $parts[0]); // Debug
            return $parts[0];
        }
    }
    
    // Fallback ke GET parameter untuk testing
    if (isset($_GET['member'])) {
        return sanitize_text_field($_GET['member']);
    }
    
    return '';
}

// Shortcode to display member name based on subdomain
add_shortcode('member_nama', 'member_name_shortcode');

function member_name_shortcode() {
    $subdomain = get_current_subdomain();
    
    // Debug: tambahkan ini sementara
    error_log('Detected subdomain: ' . $subdomain);
    
    if (empty($subdomain)) {
        return 'No subdomain detected'; // Membantu debugging
    }
    
    $user = get_user_by('login', $subdomain);
    if ($user) {
        return esc_html($user->display_name);
    }
    
    return 'User not found for subdomain: ' . esc_html($subdomain); // Membantu debugging
}

// Update Elementor Dynamic Tags registration
add_action('elementor/dynamic_tags/register', function($dynamic_tags) {
    // Change to include each tag class separately
    require_once SMD_PLUGIN_DIR . 'includes/tags/member-whatsapp-tag.php';
    $dynamic_tags->register(new SMD_Member_WhatsApp_Tag());
});