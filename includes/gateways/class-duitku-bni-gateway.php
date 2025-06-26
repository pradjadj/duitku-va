<?php

if (!defined('ABSPATH')) {
    exit;
}

class Duitku_BNI_Gateway extends Duitku_Base_Gateway {
    
    public function __construct() {
        $this->id = 'duitku_bni';
        $this->method_title = 'BNI Virtual Account';
        $this->method_description = 'Terima pembayaran melalui BNI Virtual Account';
        $this->payment_code = 'I1';
        
        // Initialize API
        $this->api = new Duitku_API();
        
        parent::__construct();
        
        // Set default values
        if (empty($this->title)) {
            $this->title = 'BNI Virtual Account';
        }
        
        if (empty($this->description)) {
            $this->description = 'Bayar menggunakan Virtual Account BNI. Nomor Virtual Account akan ditampilkan setelah checkout.';
        }
    }
    
    public function is_available() {
        if (!$this->validate_merchant_settings()) {
            return false;
        }
        
        return parent::is_available();
    }
}
