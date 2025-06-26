<?php
/**
 * Payment instructions template for Duitku VA Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

$expiry_timestamp = strtotime($expiry);
$current_time = current_time('timestamp');

// Set locale to Indonesian for month names
setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'id');
?>

<div class="duitku-payment-instructions">
    <?php if ($order->has_status('completed')): ?>
        <div class="duitku-payment-status success">
            <h3>Pesanan Selesai!</h3>
            <p>Terima kasih, pesanan anda telah selesai diproses.</p>
        </div>
    <?php elseif ($order->has_status('processing')): ?>
        <div class="duitku-payment-status success">
            <h3>Pembayaran Diterima!</h3>
            <p>Pembayaran Anda telah diterima dan sedang diproses. Terima kasih atas pesanan Anda.</p>
        </div>
    <?php elseif ($order->has_status('cancelled')): ?>
        <div class="duitku-payment-status cancelled">
            <h3>Pesanan Gagal</h3>
            <p>Pesanan ini telah gagal / dibatalkan oleh sistem.</p>
        </div>
    <?php elseif ($expiry_timestamp < $current_time): ?>
        <div class="duitku-payment-status cancelled">
            <h3>Pembayaran Kedaluwarsa</h3>
            <p>Waktu pembayaran telah habis. Silakan buat pesanan baru.</p>
        </div>
    <?php else: ?>
        <div class="duitku-va-display">
            <h3>Instruksi Pembayaran <?php echo esc_html($bank_name); ?></h3>
            
            <?php if (strpos($bank_name, 'Alfamart') !== false): ?>
                <p><strong>Kode Pembayaran:</strong></p>
            <?php else: ?>
                <p><strong>Nomor Virtual Account:</strong></p>
            <?php endif; ?>
            
            <div class="duitku-va-number"><?php echo esc_html($va_number); ?></div>
            <button type="button" class="duitku-copy-va button">Copy Nomor Virtual Account</button>
            
            <p><strong>Jumlah yang harus dibayar:</strong></p>
            <div class="duitku-amount">Rp <?php echo number_format($amount, 0, ',', '.'); ?></div>
            
            <p><strong>Batas waktu pembayaran disini:</strong></p>
            <div class="duitku-expiry"><?php echo date_i18n('j F Y H:i', $expiry_timestamp); ?> WIB</div>
        </div>

        <div class="duitku-instructions">
            <h4>Cara Pembayaran:</h4>
            
            <?php if (strpos($bank_name, 'Alfamart') !== false): ?>
                <ol>
                    <li>Kunjungi gerai Alfamart terdekat</li>
                    <li>Beritahu kasir bahwa Anda ingin melakukan pembayaran Duitku</li>
                    <li>Berikan kode pembayaran kepada kasir</li>
                    <li>Bayar sesuai dengan jumlah yang tertera</li>
                    <li>Simpan struk pembayaran sebagai bukti transaksi</li>
                    <li>Pembayaran akan dikonfirmasi secara otomatis</li>
                </ol>
            <?php else: ?>
                <ol>
                    <li>Login ke aplikasi mobile/internet banking Bank anda. ?></li>
                    <li>Pilih menu Transfer atau Bayar</li>
                    <li>Pilih Virtual Account atau VA</li>
                    <li>Masukkan nomor Virtual Account di atas</li>
                    <li>Masukkan jumlah pembayaran sesuai yang tertera</li>
                    <li>Konfirmasi pembayaran</li>
                    <li>Simpan bukti transfer</li>
                    <li>Pembayaran akan dikonfirmasi secara otomatis</li>
                </ol>
            <?php endif; ?>
        </div>

        <button type="button" class="duitku-refresh-status button" data-order-id="<?php echo $order->get_id(); ?>">
            Refresh Status Pembayaran
        </button>

        <script type="text/javascript">
            var duitku_order_data = {
                order_id: <?php echo $order->get_id(); ?>,
                expiry_timestamp: <?php echo $expiry_timestamp; ?>
            };
            
            document.addEventListener('DOMContentLoaded', function() {
                var copyButton = document.querySelector('.duitku-copy-va');
                if (copyButton) {
                    copyButton.addEventListener('click', function() {
                        var vaNumber = document.querySelector('.duitku-va-number').textContent;
                        navigator.clipboard.writeText(vaNumber).then(function() {
                            alert('Nomor Virtual Account berhasil disalin!');
                        }, function() {
                            alert('Gagal menyalin Nomor Virtual Account.');
                        });
                    });
                }
            });
        </script>
    <?php endif; ?>
</div>
