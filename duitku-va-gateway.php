<?php
/**
 * Plugin Name: Duitku VA Gateway
 * Plugin URI: https://sgnet.co.id
 * Description: Duitku Payment Gateway with VA for WooCommerce - Display VA directly on checkout page
 * Version: 1.0
 * Author: Pradja DJ
 * Author URI: https://sgnet.co.id
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.8
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DUITKU_VA_VERSION', '1.0');
define('DUITKU_VA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DUITKU_VA_PLUGIN_PATH', plugin_dir_path(__FILE__));

add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

class Duitku_VA_Gateway_Main {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->load_files();
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
        $this->init_admin();
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_api_wc_duitku_pg', array($this, 'handle_callback'));
        
        add_action('wp_ajax_duitku_check_payment_status', array($this, 'ajax_check_payment_status'));
        add_action('wp_ajax_nopriv_duitku_check_payment_status', array($this, 'ajax_check_payment_status'));
        
        add_action('duitku_check_expired_orders', array($this, 'check_expired_orders'));
        if (!wp_next_scheduled('duitku_check_expired_orders')) {
            wp_schedule_event(time(), 'hourly', 'duitku_check_expired_orders');
        }
    }
    
    public function load_files() {
        require_once DUITKU_VA_PLUGIN_PATH . 'includes/class-duitku-logger.php';
        require_once DUITKU_VA_PLUGIN_PATH . 'includes/class-duitku-base-gateway.php';
        require_once DUITKU_VA_PLUGIN_PATH . 'includes/api/class-duitku-api.php';
        require_once DUITKU_VA_PLUGIN_PATH . 'includes/api/class-duitku-callback.php';
        require_once DUITKU_VA_PLUGIN_PATH . 'includes/admin/class-duitku-admin-settings.php';
        
        require_once DUITKU_VA_PLUGIN_PATH . 'includes/gateways/class-duitku-bni-gateway.php';
        require_once DUITKU_VA_PLUGIN_PATH . 'includes/gateways/class-duitku-bri-gateway.php';
        require_once DUITKU_VA_PLUGIN_PATH . 'includes/gateways/class-duitku-mandiri-gateway.php';
        require_once DUITKU_VA_PLUGIN_PATH . 'includes/gateways/class-duitku-bsi-gateway.php';
        require_once DUITKU_VA_PLUGIN_PATH . 'includes/gateways/class-duitku-cimb-gateway.php';
        require_once DUITKU_VA_PLUGIN_PATH . 'includes/gateways/class-duitku-permata-gateway.php';
        require_once DUITKU_VA_PLUGIN_PATH . 'includes/gateways/class-duitku-alfamart-gateway.php';
    }
    
    public function add_gateways($gateways) {
        $gateways[] = 'Duitku_BNI_Gateway';
        $gateways[] = 'Duitku_BRI_Gateway';
        $gateways[] = 'Duitku_Mandiri_Gateway';
        $gateways[] = 'Duitku_BSI_Gateway';
        $gateways[] = 'Duitku_CIMB_Gateway';
        $gateways[] = 'Duitku_Permata_Gateway';
        $gateways[] = 'Duitku_Alfamart_Gateway';
        return $gateways;
    }
    
    public function init_admin() {
        new Duitku_Admin_Settings();
    }
    
    public function enqueue_scripts() {
        if (is_checkout()) {
            wp_enqueue_script(
                'duitku-checkout',
                DUITKU_VA_PLUGIN_URL . 'assets/js/duitku-checkout.js',
                array('jquery'),
                DUITKU_VA_VERSION,
                true
            );
            
            wp_localize_script('duitku-checkout', 'duitku_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('duitku_check_payment')
            ));
            
            wp_enqueue_style(
                'duitku-style',
                DUITKU_VA_PLUGIN_URL . 'assets/css/duitku-style.css',
                array(),
                DUITKU_VA_VERSION
            );
        }
    }
    
    public function handle_callback() {
        header('Content-Type: application/json');
        
        try {
            $callback = new Duitku_Callback();
            $callback->handle_callback();
        } catch (Exception $e) {
            $logger = Duitku_Logger::get_instance();
            $logger->error('Callback processing failed: ' . $e->getMessage());
            
            http_response_code(400);
            echo json_encode(array('error' => $e->getMessage()));
            exit;
        }
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=duitku') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function activate() {
        $this->create_tables();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('duitku_check_expired_orders');
    }
    
    public function check_expired_orders() {
        global $wpdb;
        
        $pending_orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT order_id FROM {$wpdb->prefix}duitku_transactions 
                WHERE status = 'pending' 
                AND created_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)",
                intval(get_option('duitku_settings')['expiry_period'])
            )
        );
        
        if (empty($pending_orders)) {
            return;
        }
        
        $logger = Duitku_Logger::get_instance();
        
        foreach ($pending_orders as $pending) {
            $order = wc_get_order($pending->order_id);
            
            if (!$order || !$order->has_status('pending')) {
                continue;
            }
            
            $order->update_status(
                'cancelled',
                __('Order automatically cancelled due to payment expiration.', 'woocommerce')
            );
            
            $wpdb->update(
                $wpdb->prefix . 'duitku_transactions',
                array('status' => 'cancelled'),
                array('order_id' => $pending->order_id),
                array('%s'),
                array('%d')
            );
            
            $logger->info(
                'Order automatically cancelled due to payment expiration',
                array('order_id' => $pending->order_id)
            );
        }
    }
    
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'duitku_transactions';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            merchant_order_id varchar(100) NOT NULL,
            va_number varchar(50) DEFAULT '',
            payment_method varchar(10) NOT NULL,
            amount decimal(10,2) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY merchant_order_id (merchant_order_id),
            KEY order_id (order_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>Duitku VA Gateway</strong> memerlukan WooCommerce untuk berfungsi.</p></div>';
    }
    
    public function ajax_check_payment_status() {
        check_ajax_referer('duitku_check_payment', 'nonce');
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(array('message' => 'Invalid order ID'));
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
            return;
        }
        
        if ($order->has_status('completed')) {
            $gateway = WC()->payment_gateways->payment_gateways()[$order->get_payment_method()];
            wp_send_json_success(array(
                'status' => 'completed',
                'redirect_url' => $gateway->get_return_url($order),
                'va_number' => $order->get_meta('_va_number'),
                'expiry_time' => date('d F Y H:i', strtotime($order->get_meta('_payment_expiry'))),
                'expiry_timestamp' => strtotime($order->get_meta('_payment_expiry'))
            ));
            return;
        }
        
        if ($order->has_status('cancelled')) {
            wp_send_json_success(array(
                'status' => 'cancelled',
                'redirect_url' => '',
                'va_number' => $order->get_meta('_va_number'),
                'expiry_time' => date('d F Y H:i', strtotime($order->get_meta('_payment_expiry'))),
                'expiry_timestamp' => strtotime($order->get_meta('_payment_expiry'))
            ));
            return;
        }
        
        $payment_method = $order->get_payment_method();
        $available_gateways = WC()->payment_gateways->payment_gateways();
        
        if (!isset($available_gateways[$payment_method])) {
            wp_send_json_error(array('message' => 'Payment method not found'));
            return;
        }
        
        $gateway = $available_gateways[$payment_method];
        
        if (!$order->has_status('pending')) {
            wp_send_json_success(array(
                'status' => $order->get_status(),
                'redirect_url' => '',
                'va_number' => $order->get_meta('_va_number'),
                'expiry_time' => date('d F Y H:i', strtotime($order->get_meta('_payment_expiry'))),
                'expiry_timestamp' => strtotime($order->get_meta('_payment_expiry'))
            ));
            return;
        }
        
        $merchant_settings = get_option('duitku_settings', array());
        $prefix = isset($merchant_settings['merchant_order_prefix']) ? $merchant_settings['merchant_order_prefix'] : 'TRX-';
        $merchant_order_id = $prefix . $order_id;
        $api = new Duitku_API();
        $response = $api->check_transaction_status($merchant_order_id);
        
        if (isset($response['error'])) {
            wp_send_json_error(array('message' => $response['error']));
            return;
        }
        
        $status = '';
        $redirect_url = '';
        
        if (isset($response['statusCode'])) {
            switch ($response['statusCode']) {
                case '00':
                    if (!$order->has_status('completed')) {
                        $recorded_reference = $order->get_meta('_duitku_payment_reference');
                        if (empty($recorded_reference)) {
                            $order->update_meta_data('_duitku_payment_reference', $response['reference']);
                            $order->set_status('processing', sprintf(
                                __('Payment completed via Duitku. Reference: %s', 'woocommerce'),
                                $response['reference']
                            ));
                            $order->save();
                        }
                    }
                    $status = 'completed';
                    $redirect_url = $gateway->get_return_url($order);
                    break;
                    
                case '01':
                    if (!$order->has_status('pending')) {
                        $order->update_status('pending', __('Awaiting payment confirmation from Duitku.', 'woocommerce'));
                    }
                    $status = 'pending';
                    break;
                    
                case '02':
                    if ($order->has_status('pending')) {
                        $order->update_status('cancelled', __('Payment cancelled or expired.', 'woocommerce'));
                    }
                    $status = 'cancelled';
                    break;
                    
                default:
                    $status = 'unknown';
                    break;
            }
        }
        
        $va_number = $order->get_meta('_va_number');
        $expiry = $order->get_meta('_payment_expiry');
        $expiry_timestamp = strtotime($expiry);
        
        wp_send_json_success(array(
            'status' => $status,
            'redirect_url' => $redirect_url,
            'va_number' => $va_number,
            'expiry_time' => date('d F Y H:i', $expiry_timestamp),
            'expiry_timestamp' => $expiry_timestamp
        ));
    }
}

new Duitku_VA_Gateway_Main();