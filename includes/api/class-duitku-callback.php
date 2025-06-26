<?php

if (!defined('ABSPATH')) {
    exit;
}

class Duitku_Callback {
    
    private $logger;
    private $api;
    
    public function __construct() {
        $this->logger = Duitku_Logger::get_instance();
        $this->api = new Duitku_API();
    }
    
    public function handle_callback() {
        try {
            $callback_data = $this->get_callback_data();
            Duitku_Logger::log_callback($callback_data);
            
            if (!$this->api->validate_signature($callback_data)) {
                throw new Exception('Invalid signature');
            }
            
            $this->process_callback($callback_data);
            $this->send_response(array('success' => true));
            
        } catch (Exception $e) {
            $this->logger->error('Callback processing failed: ' . $e->getMessage());
            $this->send_response(array('error' => $e->getMessage()), 400);
        }
    }
    
    private function get_callback_data() {
        $raw_post = file_get_contents('php://input');
        
        if (!empty($raw_post)) {
            $data = json_decode($raw_post, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }
        
        if (!empty($_POST)) {
            return $_POST;
        }
        
        throw new Exception('Invalid or empty callback data');
    }
    
    private function process_callback($data) {
        global $wpdb;
        
        if (!isset($data['merchantOrderId'])) {
            throw new Exception('Missing merchantOrderId in callback data');
        }
        
        $merchant_order_id = $data['merchantOrderId'];
        $order_id = $this->get_order_id_from_merchant_order_id($merchant_order_id);
        
        if (!$order_id) {
            throw new Exception('Order not found for merchant order ID: ' . $merchant_order_id);
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            throw new Exception('WC Order not found: ' . $order_id);
        }
        
        $wpdb->update(
            $wpdb->prefix . 'duitku_transactions',
            array('status' => $this->get_transaction_status($data['resultCode'])),
            array('merchant_order_id' => $merchant_order_id),
            array('%s'),
            array('%s')
        );
        
        if (!isset($data['resultCode'])) {
            throw new Exception('Missing resultCode in callback data');
        }
        
        switch ($data['resultCode']) {
            case '00':
                if (!$order->has_status('completed')) {
                    $order->update_meta_data('_duitku_reference', $data['reference']);
                    $order->update_meta_data('_duitku_settlement_date', $data['settlementDate']);
                    $order->set_status('processing', sprintf(
                        __('Payment completed via Duitku. Reference: %s', 'woocommerce'),
                        $data['reference']
                    ));
                    $order->save();
                }
                break;
                
            case '01':
                $order->update_status('pending', __('Awaiting payment confirmation from Duitku.', 'woocommerce'));
                break;
                
            case '02':
                if ($order->has_status('pending')) {
                    $order->update_status('cancelled', __('Payment cancelled or expired.', 'woocommerce'));
                }
                break;
                
            default:
                throw new Exception('Unknown result code: ' . $data['resultCode']);
        }
        
        $this->logger->info(
            'Callback processed successfully',
            array(
                'order_id' => $order_id,
                'status' => $data['resultCode']
            )
        );
    }
    
    private function get_order_id_from_merchant_order_id($merchant_order_id) {
        $merchant_settings = get_option('duitku_settings', array());
        $prefix = isset($merchant_settings['merchant_order_prefix']) ? $merchant_settings['merchant_order_prefix'] : 'TRX-';

        $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';
        if (preg_match($pattern, $merchant_order_id, $matches)) {
            return $matches[1];
        }
        return false;
    }
    
    private function get_transaction_status($result_code) {
        switch ($result_code) {
            case '00': return 'completed';
            case '01': return 'pending';
            case '02': return 'cancelled';
            default: return 'failed';
        }
    }
    
    private function send_response($data, $status_code = 200) {
        header('Content-Type: application/json');
        http_response_code($status_code);
        echo json_encode($data);
        exit;
    }
}