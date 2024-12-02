
<?php
class SMD_Elementor_Tags extends \Elementor\Core\DynamicTags\Tag {
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
        $subdomain_handler = new SMD_Subdomain_Handler();
        $phone = $subdomain_handler->get_member_data('phone');
        echo 'https://wa.me/' . preg_replace('/[^0-9]/', '', $phone);
    }
}