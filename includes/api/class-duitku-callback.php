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
            // Get callback data
            $callback_data = $this->get_callback_data();
            
            // Log callback
            Duitku_Logger::log_callback($callback_data);
            
            // Validate signature
            if (!$this->api->validate_signature($callback_data)) {
                throw new Exception('Invalid signature');
            }
            
            // Process callback
            $this->process_callback($callback_data);
            
            // Return success response
            echo json_encode(array('success' => true));
            exit;
            
        } catch (Exception $e) {
            $this->logger->error('Callback processing failed: ' . $e->getMessage());
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(array('error' => $e->getMessage()));
            exit;
        }
    }
    
    private function get_callback_data() {
        $raw_post = file_get_contents('php://input');
        $data = json_decode($raw_post, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data received');
        }
        
        return $data;
    }
    
    private function process_callback($data) {
        global $wpdb;
        
        // Extract merchant order ID
        $merchant_order_id = $data['merchantOrderId'];
        
        // Get order ID from merchant order ID
        $order_id = $this->get_order_id_from_merchant_order_id($merchant_order_id);
        if (!$order_id) {
            throw new Exception('Order not found for merchant order ID: ' . $merchant_order_id);
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            throw new Exception('WC Order not found: ' . $order_id);
        }
        
        // Update transaction status in custom table
        $wpdb->update(
            $wpdb->prefix . 'duitku_transactions',
            array(
                'status' => $this->get_transaction_status($data['resultCode'])
            ),
            array(
                'merchant_order_id' => $merchant_order_id
            ),
            array('%s'),
            array('%s')
        );
        
        // Process based on result code
        switch ($data['resultCode']) {
            case '00': // Success
                // Check if order is already completed
                if ($order->has_status('completed')) {
                    $this->logger->info('Order already completed', array('order_id' => $order_id));
                    return;
                }
                
                // Save additional payment info first
                $order->update_meta_data('_duitku_reference', $data['reference']);
                $order->update_meta_data('_duitku_settlement_date', $data['settlementDate']);
                
                // Update order status (modern way without deprecated hooks)
                $order->set_status('processing', sprintf(
                    __('Payment completed via Duitku. Reference: %s', 'woocommerce'),
                    $data['reference']
                ));
                $order->save();
                
                break;
                
            case '01': // Pending
                $order->update_status(
                    'pending',
                    __('Awaiting payment confirmation from Duitku.', 'woocommerce')
                );
                break;
                
            case '02': // Cancelled
                // Only cancel if order is still pending
                if ($order->has_status('pending')) {
                    $order->update_status(
                        'cancelled',
                        __('Payment cancelled or expired.', 'woocommerce')
                    );
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
        // Extract order ID from prefix-{order_id} format
        $merchant_settings = get_option('duitku_settings', array());
        $prefix = isset($merchant_settings['merchant_order_prefix']) ? $merchant_settings['merchant_order_prefix'] : 'DPAY-';

        $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';
        if (preg_match($pattern, $merchant_order_id, $matches)) {
            return $matches[1];
        }
        return false;
    }
    
    private function get_transaction_status($result_code) {
        switch ($result_code) {
            case '00':
                return 'completed';
            case '01':
                return 'pending';
            case '02':
                return 'cancelled';
            default:
                return 'failed';
        }
    }
}
