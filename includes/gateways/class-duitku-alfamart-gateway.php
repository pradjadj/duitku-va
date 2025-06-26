<?php

if (!defined('ABSPATH')) {
    exit;
}

class Duitku_Alfamart_Gateway extends Duitku_Base_Gateway {
    
    public function __construct() {
        $this->id = 'duitku_alfamart';
        $this->method_title = 'Alfamart';
        $this->method_description = 'Terima pembayaran melalui Alfamart Group';
        $this->payment_code = 'FT';
        
        // Initialize API
        $this->api = new Duitku_API();
        
        parent::__construct();
        
        // Set default values
        if (empty($this->title)) {
            $this->title = 'Bayar di Alfamart';
        }
        
        if (empty($this->description)) {
            $this->description = 'Bayar pesanan Anda di gerai Alfamart terdekat. Kode pembayaran akan ditampilkan setelah checkout.';
        }
    }
    
    public function is_available() {
        if (!$this->validate_merchant_settings()) {
            return false;
        }
        
        return parent::is_available();
    }
}
