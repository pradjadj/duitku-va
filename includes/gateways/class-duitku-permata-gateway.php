<?php

if (!defined('ABSPATH')) {
    exit;
}

class Duitku_Permata_Gateway extends Duitku_Base_Gateway {
    
    public function __construct() {
        $this->id = 'duitku_permata';
        $this->method_title = 'Permata Bank Virtual Account';
        $this->method_description = 'Terima pembayaran melalui Permata Bank Virtual Account';
        $this->payment_code = 'BT';
        
        // Initialize API
        $this->api = new Duitku_API();
        
        parent::__construct();
        
        // Set default values
        if (empty($this->title)) {
            $this->title = 'Permata Bank Virtual Account';
        }
        
        if (empty($this->description)) {
            $this->description = 'Bayar menggunakan Virtual Account Permata Bank. Nomor Virtual Account akan ditampilkan setelah checkout.';
        }
    }
    
    public function is_available() {
        if (!$this->validate_merchant_settings()) {
            return false;
        }
        
        return parent::is_available();
    }
}
