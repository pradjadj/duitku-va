<?php

if (!defined('ABSPATH')) {
    exit;
}

class Duitku_BSI_Gateway extends Duitku_Base_Gateway {
    
    public function __construct() {
        $this->id = 'duitku_bsi';
        $this->method_title = 'BSI Virtual Account';
        $this->method_description = 'Terima pembayaran melalui BSI Virtual Account';
        $this->payment_code = 'BV';
        
        // Initialize API
        $this->api = new Duitku_API();
        
        parent::__construct();
        
        // Set default values
        if (empty($this->title)) {
            $this->title = 'BSI Virtual Account';
        }
        
        if (empty($this->description)) {
            $this->description = 'Bayar menggunakan Virtual Account BSI. Nomor Virtual Account akan ditampilkan setelah checkout.';
        }
    }
    
    public function is_available() {
        if (!$this->validate_merchant_settings()) {
            return false;
        }
        
        return parent::is_available();
    }
}
