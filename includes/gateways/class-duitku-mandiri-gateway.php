<?php

if (!defined('ABSPATH')) {
    exit;
}

class Duitku_Mandiri_Gateway extends Duitku_Base_Gateway {
    
    public function __construct() {
        $this->id = 'duitku_mandiri';
        $this->method_title = 'Mandiri Virtual Account';
        $this->method_description = 'Terima pembayaran melalui Mandiri Virtual Account';
        $this->payment_code = 'M2';
        
        // Initialize API
        $this->api = new Duitku_API();
        
        parent::__construct();
        
        // Set default values
        if (empty($this->title)) {
            $this->title = 'Mandiri Virtual Account';
        }
        
        if (empty($this->description)) {
            $this->description = 'Bayar menggunakan Virtual Account Mandiri. Nomor Virtual Account akan ditampilkan setelah checkout.';
        }
    }
    
    public function is_available() {
        if (!$this->validate_merchant_settings()) {
            return false;
        }
        
        return parent::is_available();
    }
}
