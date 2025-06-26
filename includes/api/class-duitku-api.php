<?php

if (!defined('ABSPATH')) {
    exit;
}

class Duitku_API {
    
    private $merchant_code;
    private $api_key;
    private $environment;
    private $logger;
    
    public function __construct() {
        $settings = get_option('duitku_settings', array());
        $this->merchant_code = $settings['merchant_code'] ?? '';
        $this->api_key = $settings['api_key'] ?? '';
        $this->environment = $settings['environment'] ?? 'sandbox';
        $this->logger = Duitku_Logger::get_instance();
    }
    
    public function create_transaction($data) {
        $endpoint = $this->get_api_endpoint();
        
        $this->logger->info('Creating transaction', array('data' => $data));
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($data),
            'timeout' => 30,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->logger->error('API request failed: ' . $error_message);
            Duitku_Logger::log_api_request($endpoint, $data, null, $error_message);
            return array('error' => $error_message);
        }
        
        $body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'Invalid JSON response';
            $this->logger->error($error_message . ': ' . $body);
            Duitku_Logger::log_api_request($endpoint, $data, $body, $error_message);
            return array('error' => $error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            $error_message = 'HTTP Error: ' . $response_code;
            $this->logger->error($error_message, array('response' => $decoded_response));
            Duitku_Logger::log_api_request($endpoint, $data, $decoded_response, $error_message);
            return array('error' => $error_message);
        }
        
        // Check if response contains error
        if (isset($decoded_response['statusCode']) && $decoded_response['statusCode'] !== '00') {
            $error_message = $decoded_response['statusMessage'] ?? 'Unknown error';
            $this->logger->error('Duitku API error: ' . $error_message, array('response' => $decoded_response));
            Duitku_Logger::log_api_request($endpoint, $data, $decoded_response, $error_message);
            return array('error' => $error_message);
        }
        
        $this->logger->info('Transaction created successfully', array('response' => $decoded_response));
        Duitku_Logger::log_api_request($endpoint, $data, $decoded_response);
        
        return $decoded_response;
    }
    
    public function check_transaction_status($merchant_order_id) {
        // For checking transaction status if needed
        $endpoint = $this->get_status_endpoint();
        
        $data = array(
            'merchantCode' => $this->merchant_code,
            'merchantOrderId' => $merchant_order_id,
            'signature' => md5($this->merchant_code . $merchant_order_id . $this->api_key)
        );
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($data),
            'timeout' => 30,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->logger->error('Status check failed: ' . $error_message);
            return array('error' => $error_message);
        }
        
        $body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'Invalid JSON response';
            $this->logger->error($error_message . ': ' . $body);
            return array('error' => $error_message);
        }
        
        return $decoded_response;
    }
    
    private function get_api_endpoint() {
        if ($this->environment === 'production') {
            return 'https://passport.duitku.com/webapi/api/merchant/v2/inquiry';
        } else {
            return 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry';
        }
    }
    
    private function get_status_endpoint() {
        if ($this->environment === 'production') {
            return 'https://passport.duitku.com/webapi/api/merchant/transactionStatus';
        } else {
            return 'https://sandbox.duitku.com/webapi/api/merchant/transactionStatus';
        }
    }
    
    public function validate_signature($data) {
        $required_params = array(
            'merchantCode', 'amount', 'merchantOrderId', 'productDetail',
            'additionalParam', 'resultCode', 'paymentCode', 'merchantUserId',
            'reference', 'signature'
        );
        
        foreach ($required_params as $param) {
            if (!isset($data[$param])) {
                $this->logger->error('Missing callback parameter: ' . $param);
                return false;
            }
        }
        
        // Generate expected signature
        $signature_string = $data['merchantCode'] . $data['amount'] . $data['merchantOrderId'] . $this->api_key;
        $expected_signature = md5($signature_string);
        
        if ($data['signature'] !== $expected_signature) {
            $this->logger->error('Invalid callback signature', array(
                'received' => $data['signature'],
                'expected' => $expected_signature
            ));
            return false;
        }
        
        return true;
    }
    
    public function is_configured() {
        return !empty($this->merchant_code) && !empty($this->api_key);
    }
}
