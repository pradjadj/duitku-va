<?php

if (!defined('ABSPATH')) {
    exit;
}

class Duitku_Admin_Settings {
    
    private $option_name = 'duitku_settings';

    public function __construct() {
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_duitku', array($this, 'settings_tab'));
        add_action('woocommerce_update_options_duitku', array($this, 'update_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function add_settings_tab($settings_tabs) {
        $settings_tabs['duitku'] = __('Duitku Settings', 'woocommerce');
        return $settings_tabs;
    }
    
    public function settings_tab() {
        woocommerce_admin_fields($this->get_settings());
    }
    
    public function update_settings() {
        $settings = $this->get_settings();
        $options = array();
        foreach ($settings as $setting) {
            if (isset($setting['id'])) {
                $value = isset($_POST[$setting['id']]) ? sanitize_text_field(wp_unslash($_POST[$setting['id']])) : '';
                $options[$setting['id']] = $value;
            }
        }
        update_option($this->option_name, $options);
    }
    
    public function get_settings() {
        $callback_url = home_url('/wc-api/wc_duitku_pg');
        $options = get_option($this->option_name, array());
        
        $settings = array(
            'section_title' => array(
                'name' => __('Duitku Payment Gateway Settings', 'woocommerce'),
                'type' => 'title',
                'desc' => __('Configure your Duitku merchant settings. These settings will be used across all Duitku payment methods.', 'woocommerce'),
                'id' => 'duitku_section_title'
            ),
            
            'merchant_code' => array(
                'name' => __('Merchant Code', 'woocommerce'),
                'type' => 'text',
                'desc' => __('Enter your Duitku merchant code.', 'woocommerce'),
                'id' => 'merchant_code',
                'desc_tip' => true,
                'default' => isset($options['merchant_code']) ? $options['merchant_code'] : '',
            ),
            
            'api_key' => array(
                'name' => __('API Key', 'woocommerce'),
                'type' => 'password',
                'desc' => __('Enter your Duitku API key.', 'woocommerce'),
                'id' => 'api_key',
                'desc_tip' => true,
                'default' => isset($options['api_key']) ? $options['api_key'] : '',
            ),
            
            'environment' => array(
                'name' => __('Environment', 'woocommerce'),
                'type' => 'select',
                'desc' => __('Select the environment for Duitku API.', 'woocommerce'),
                'id' => 'environment',
                'options' => array(
                    'sandbox' => __('Sandbox', 'woocommerce'),
                    'production' => __('Production', 'woocommerce'),
                ),
                'default' => isset($options['environment']) ? $options['environment'] : 'sandbox',
                'desc_tip' => true,
            ),
            
            'expiry_period' => array(
                'name' => __('Payment Expiry Period', 'woocommerce'),
                'type' => 'number',
                'desc' => __('Payment expiry time in minutes (1-1440 minutes).', 'woocommerce'),
                'id' => 'expiry_period',
                'default' => isset($options['expiry_period']) ? $options['expiry_period'] : '720',
                'custom_attributes' => array(
                    'min' => '1',
                    'max' => '1440',
                ),
                'desc_tip' => true,
            ),
            
            'enable_logging' => array(
                'name' => __('Enable Logging', 'woocommerce'),
                'type' => 'select',
                'desc' => __('Enable logging for Duitku transactions and errors.', 'woocommerce'),
                'id' => 'enable_logging',
                'options' => array(
                    'yes' => __('Yes', 'woocommerce'),
                    'no' => __('No', 'woocommerce'),
                ),
                'default' => 'no',
                'desc_tip' => true,
                'value' => isset($options['enable_logging']) ? $options['enable_logging'] : 'no',
            ),
            
            'merchant_order_prefix' => array(
                'name' => __('Merchant Order ID Prefix', 'woocommerce'),
                'type' => 'text',
                'desc' => __('Prefix to use for merchant order IDs. eg "TRX-12345"', 'woocommerce'),
                'id' => 'merchant_order_prefix',
                'default' => isset($options['merchant_order_prefix']) ? $options['merchant_order_prefix'] : 'TRX-',
                'desc_tip' => true,
            ),
            
            'callback_info' => array(
                'name' => __('Callback URL Information', 'woocommerce'),
                'type' => 'title',
                'desc' => sprintf(
                    __('Use this URL in your Duitku merchant dashboard for payment callbacks:<br><br>
                    <strong>Callback URL:</strong><br>
                    <code>%s</code><br><br>
                    <strong>Important:</strong><br>
                    - Make sure your server can receive POST requests<br>
                    - Content-Type must be application/json<br>
                    - No basic authentication required', 'woocommerce'),
                    esc_url($callback_url)
                ),
                'id' => 'duitku_callback_info'
            ),
            
            'section_end' => array(
                'type' => 'sectionend',
                'id' => 'duitku_section_end'
            )
        );
        
        return apply_filters('woocommerce_duitku_settings', $settings);
    }
    
    public function enqueue_admin_scripts($hook) {
        if ('woocommerce_page_wc-settings' !== $hook) {
            return;
        }
        
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'duitku') {
            return;
        }
        
        wp_enqueue_style(
            'duitku-admin',
            DUITKU_VA_PLUGIN_URL . 'assets/css/duitku-admin.css',
            array(),
            DUITKU_VA_VERSION
        );
    }
}