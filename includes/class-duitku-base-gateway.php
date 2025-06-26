<?php

if (!defined('ABSPATH')) {
    exit;
}

date_default_timezone_set('Asia/Jakarta');

abstract class Duitku_Base_Gateway extends WC_Payment_Gateway {
    
    protected $logger;
    protected $api;
    protected $payment_code;
    protected $merchant_settings;
    
    public function __construct() {
        $this->logger = Duitku_Logger::get_instance();
        $this->merchant_settings = get_option('duitku_settings', array());

        if (empty($this->merchant_settings['expiry_period'])) {
            $this->merchant_settings['expiry_period'] = 1440;
        }
        
        $this->has_fields = false;
        $this->order_button_text = __('Place order', 'woocommerce');
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thank_you_page'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable this payment method', 'woocommerce'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('Payment method title that the customer will see on your checkout.', 'woocommerce'),
                'default' => $this->method_title,
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
                'default' => '',
                'desc_tip' => true,
            )
        );
    }
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        try {
            $transaction_data = $this->prepare_transaction_data($order);
            $response = $this->api->create_transaction($transaction_data);
            
            if (!$response || isset($response['error'])) {
                throw new Exception(isset($response['error']) ? $response['error'] : 'Failed to create transaction');
            }
            
            $this->save_transaction_details($order, $response);
            $order->update_status('pending', __('Awaiting payment via ', 'woocommerce') . $this->title);
            WC()->cart->empty_cart();
            
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
            
        } catch (Exception $e) {
            $this->logger->error('Payment processing failed: ' . $e->getMessage(), array('order_id' => $order_id));
            wc_add_notice(__('Payment error:', 'woocommerce') . ' ' . $e->getMessage(), 'error');
            return array('result' => 'fail');
        }
    }
    
    protected function prepare_transaction_data($order) {
        $amount = $order->get_total();
        $merchant_code = $this->merchant_settings['merchant_code'];
        $merchant_settings = get_option('duitku_settings', array());
        $prefix = isset($merchant_settings['merchant_order_prefix']) ? $merchant_settings['merchant_order_prefix'] : 'TRX-';
        $merchant_order_id = $prefix . $order->get_id();
        $api_key = $this->merchant_settings['api_key'];
        
        $signature = md5($merchant_code . $merchant_order_id . $amount . $api_key);
        
        return array(
            'merchantCode' => $merchant_code,
            'paymentAmount' => $amount,
            'merchantOrderId' => $merchant_order_id,
            'productDetails' => $this->get_product_details($order),
            'customerVaName' => get_bloginfo('name'),
            'email' => $order->get_billing_email(),
            'phoneNumber' => $order->get_billing_phone(),
            'paymentMethod' => $this->payment_code,
            'returnUrl' => $this->get_return_url($order),
            'callbackUrl' => $this->get_callback_url(),
            'signature' => $signature,
            'expiryPeriod' => $this->merchant_settings['expiry_period']
        );
    }
    
    protected function get_product_details($order) {
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = $item->get_name();
        }
        return implode(', ', $items);
    }
    
    protected function save_transaction_details($order, $response) {
        $va_number = '';
        if (isset($response['vaNumber'])) {
            $va_number = $response['vaNumber'];
        } elseif (isset($response['paymentCode'])) {
            $va_number = $response['paymentCode'];
        }
        
        $order->update_meta_data('_va_number', $va_number);
        $order->update_meta_data('_payment_expiry', date('Y-m-d H:i:s', strtotime("+{$this->merchant_settings['expiry_period']} minutes")));
        $order->save();
    }
    
    public function thank_you_page($order_id) {
        $order = wc_get_order($order_id);
        if ($order->get_payment_method() === $this->id) {
            $va_number = $order->get_meta('_va_number');
            $expiry = $order->get_meta('_payment_expiry');
            $this->display_payment_instructions($order, $va_number, $expiry);
        }
    }
    
    protected function display_payment_instructions($order, $va_number, $expiry) {
        $expiry_timestamp = strtotime($expiry);
        $current_time = current_time('timestamp');
        
        $bulan = array(
            'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
            'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
            'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
            'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
        );
        
        $expiry_date = date('d F Y H:i', $expiry_timestamp);
        foreach ($bulan as $eng => $ind) {
            $expiry_date = str_replace($eng, $ind, $expiry_date);
        }

        echo '<div class="duitku-payment-instructions">';
        
        if ($order->has_status('completed')) {
            echo '<div class="duitku-payment-status success">';
            echo '<h3>Pesanan Selesai!</h3>';
            echo '<p>Terima kasih, pesanan anda telah selesai diproses.</p>';
            echo '</div>';
        } elseif ($order->has_status('processing')) {
            echo '<div class="duitku-payment-status success">';
            echo '<h3>Pembayaran Diterima!</h3>';
            echo '<p>Pembayaran Anda telah diterima dan sedang diproses. Terima kasih atas pesanan Anda.</p>';
            echo '</div>';
        } elseif ($order->has_status('cancelled')) {
            echo '<div class="duitku-payment-status cancelled">';
            echo '<h3>Pesanan Gagal</h3>';
            echo '<p>Pesanan ini telah gagal / dibatalkan oleh sistem.</p>';
            echo '</div>';
        } elseif ($expiry_timestamp < $current_time) {
            echo '<div class="duitku-payment-status cancelled">';
            echo '<h3>Pembayaran Kedaluwarsa</h3>';
            echo '<p>Waktu pembayaran telah habis. Silakan buat pesanan baru.</p>';
            echo '</div>';
        } else {
            echo '<div class="duitku-va-display">';
            echo '<h3>Informasi Detail Pembayaran</h3>';
            
            if ($this->payment_code === 'FT') {
                echo '<p><strong>Kode Pembayaran:</strong></p>';
            } else {
                echo '<p style="margin-bottom:0;"><strong>Nomor Virtual Account:</strong></p>';
            }
            
            echo '<div class="duitku-va-number">' . esc_html($va_number) . '</div>';
            echo '<p style="margin-bottom:0;"><strong>Jumlah yang harus dibayar:</strong></p>';
            echo '<div class="duitku-amount">Rp ' . number_format($order->get_total(), 0, ',', '.') . '</div>';
            echo '<p><strong>Bayar pesanan anda sebelum ' . $expiry_date . ' WIB</strong></p>';

            echo '<button type="button" class="duitku-copy-btn" id="copy-va-btn">Copy Nomor VA</button>';
            echo '<button type="button" class="duitku-copy-btn" id="copy-amount-btn">Copy Nominal</button>';
            echo '</div>';
            
            $this->display_bank_specific_instructions();
            
            echo '<button type="button" class="duitku-refresh-status button" data-order-id="' . $order->get_id() . '">Refresh Status Pembayaran</button>';
        }
        
        echo '</div>';
        
        echo '<script type="text/javascript">';
        echo 'var duitku_order_data = {';
        echo 'order_id: ' . $order->get_id() . ',';
        echo 'expiry_timestamp: ' . $expiry_timestamp;
        echo '};';
        echo 'document.addEventListener("DOMContentLoaded", function() {';
        echo '  var copyVaBtn = document.getElementById("copy-va-btn");';
        echo '  if(copyVaBtn) {';
        echo '    copyVaBtn.addEventListener("click", function() {';
        echo '      var vaNumber = document.querySelector(".duitku-va-number").textContent.replace(/\\D/g, "");';
        echo '      navigator.clipboard.writeText(vaNumber).then(function() {';
        echo '        alert("Nomor Virtual Account berhasil disalin!");';
        echo '      }, function() {';
        echo '        alert("Gagal menyalin Nominal Pembayaran.");';
        echo '      });';
        echo '    });';
        echo '  }';
        echo '});';
        echo '</script>';
    }
    
    protected function display_bank_specific_instructions() {
        echo '<div class="duitku-instructions">';
        echo '<h4>Cara Pembayaran:</h4>';
        
        if ($this->payment_code === 'FT') {
            echo '<ol>';
            echo '<li>Kunjungi gerai Alfamart/Alfamidi/Pegadaian/POS Indonesia terdekat</li>';
            echo '<li>Beritahu kasir bahwa Anda ingin melakukan pembayaran ke <b>Finpay</b></li>';
            echo '<li>Berikan kode pembayaran diatas kepada kasir</li>';
            echo '<li>Bayar sesuai dengan jumlah yang tertera (mungkin akan ada biaya tambahan saat di kasir)</li>';
            echo '<li>Simpan struk pembayaran sebagai bukti transaksi</li>';
            echo '<li>Pembayaran akan terkonfirmasi secara otomatis</li>';
            echo '</ol>';
        } else {
            echo '<ol>';
            echo '<li>Login ke aplikasi mobile banking atau internet banking anda</li>';
            echo '<li>Pilih menu Transfer atau Bayar</li>';
            echo '<li>Pilih Virtual Account atau VA</li>';
            echo '<li>Masukkan nomor Virtual Account di atas</li>';
            echo '<li>Masukkan jumlah pembayaran sesuai yang tertera</li>';
            echo '<li>Konfirmasi pembayaran</li>';
            echo '<li>Simpan bukti transfer</li>';
            echo '<li>Pembayaran akan terkonfirmasi secara otomatis</li>';
            echo '</ol>';
        }
        
        echo '</div>';
    }
    
    protected function get_callback_url() {
        return home_url('/wc-api/wc_duitku_pg');
    }
    
    public function validate_merchant_settings() {
        return !empty($this->merchant_settings['merchant_code']) && 
               !empty($this->merchant_settings['api_key']) && 
               !empty($this->merchant_settings['environment']) && 
               !empty($this->merchant_settings['expiry_period']);
    }
}