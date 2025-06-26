<?php

if (!defined('ABSPATH')) {
    exit;
}

class Duitku_Logger {
    
    private static $instance = null;
    private $logger;
    private $logger_context;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (function_exists('wc_get_logger')) {
            $this->logger = wc_get_logger();
            $this->logger_context = array('source' => 'duitku-va');
        } else {
            $this->logger = null;
        }
    }
    
    public function log($level, $message, $context = array()) {
        if (!$this->is_logging_enabled()) {
            return;
        }
        
        if ($this->logger) {
            $context = array_merge($this->logger_context, $context);
            $this->logger->log($level, $message, $context);
        } else {
            error_log("[Duitku VA {$level}] {$message} " . json_encode($context));
        }
    }
    
    public function info($message, $context = array()) {
        $this->log('info', $message, $context);
    }
    
    public function error($message, $context = array()) {
        $this->log('error', $message, $context);
    }
    
    public function warning($message, $context = array()) {
        $this->log('warning', $message, $context);
    }
    
    public function debug($message, $context = array()) {
        $this->log('debug', $message, $context);
    }
    
    private function is_logging_enabled() {
        $settings = get_option('duitku_settings', array());
        if (!isset($settings['enable_logging'])) {
            return false;
        }
        
        return $settings['enable_logging'] === 'yes' || $settings['enable_logging'] === true;
    }
    
    public static function log_transaction($order_id, $message, $level = 'info') {
        $logger = self::get_instance();
        $context = array('order_id' => $order_id);
        $logger->log($level, $message, $context);
    }
    
    public static function log_api_request($endpoint, $request_data, $response_data = null, $error = null) {
        $logger = self::get_instance();
        
        $message = "API Request to: {$endpoint}";
        $context = array(
            'endpoint' => $endpoint,
            'request' => $request_data
        );
        
        if ($response_data) {
            $context['response'] = $response_data;
        }
        
        if ($error) {
            $context['error'] = $error;
            $logger->error($message . " - Error: {$error}", $context);
        } else {
            $logger->info($message, $context);
        }
    }
    
    public static function log_callback($callback_data, $order_id = null) {
        $logger = self::get_instance();
        
        $message = "Callback received";
        $context = array(
            'callback_data' => $callback_data
        );
        
        if ($order_id) {
            $context['order_id'] = $order_id;
        }
        
        $logger->info($message, $context);
    }
}
