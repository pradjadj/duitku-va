<?php

if (!defined('ABSPATH')) {
    exit;
}

class Duitku_BRI_Gateway extends Duitku_Base_Gateway {
    
    public function __construct() {
        $this->id = 'duitku_bri';
        $this->method_title = 'BRI Virtual Account';
        $this->method_description = 'Terima pembayaran melalui BRI Virtual Account';
        $this->payment_code = 'BR';
        
        // Initialize API
        $this->api = new Duitku_API();
        
        parent::__construct();
        
        // Set default values
        if (empty($this->title)) {
            $this->title = 'BRI Virtual Account';
        }
        
        if (empty($this->description)) {
            $this->description = 'Bayar menggunakan Virtual Account BRI. Nomor Virtual Account akan ditampilkan setelah checkout.';
        }
    }
    
    public function is_available() {
        if (!$this->validate_merchant_settings()) {
            return false;
        }
        
        return parent::is_available();
    }
}
