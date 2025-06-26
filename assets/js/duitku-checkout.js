jQuery(document).ready(function($) {
    var duitkuCheckInterval;
    var checkCount = 0;
    var maxChecks = 600; // 30 minutes (600 * 3 seconds)
    var paymentCompleted = false;
    var paymentCancelled = false;
    
    // Start payment checking after order is placed
    function startPaymentCheck(orderId) {
        // Don't start if payment is already completed or cancelled
        if (paymentCompleted || paymentCancelled) {
            return;
        }
        
        // Check if order is already completed or cancelled on page load
        if ($('.duitku-payment-status.success').length > 0) {
            paymentCompleted = true;
            return;
        }
        
        if ($('.duitku-payment-status.cancelled').length > 0) {
            paymentCancelled = true;
            return;
        }
        
        if (duitkuCheckInterval) {
            clearInterval(duitkuCheckInterval);
        }
        
        checkCount = 0;
        
        duitkuCheckInterval = setInterval(function() {
            checkPaymentStatus(orderId);
            checkCount++;
            
            // Stop checking after max attempts
            if (checkCount >= maxChecks) {
                clearInterval(duitkuCheckInterval);
                showPaymentTimeout();
            }
        }, 3000); // Check every 3 seconds
    }
    
    // Check payment status via AJAX
    function checkPaymentStatus(orderId) {
        // Don't check if payment is already completed or cancelled
        if (paymentCompleted || paymentCancelled) {
            return;
        }
        
        $.ajax({
            url: duitku_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'duitku_check_payment_status',
                order_id: orderId,
                nonce: duitku_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.status === 'completed') {
                        paymentCompleted = true;
                        clearInterval(duitkuCheckInterval);
                        showPaymentSuccess();
                        // Redirect to order received page
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 2000);
                    } else if (response.data.status === 'cancelled') {
                        paymentCancelled = true;
                        clearInterval(duitkuCheckInterval);
                        showPaymentCancelled();
                    }
                    // Update payment info if needed
                    updatePaymentInfo(response.data);
                }
            },
            error: function() {
                // Continue checking on error
            }
        });
    }
    
    // Show payment success message
    function showPaymentSuccess() {
        var successHtml = '<div class="duitku-payment-status success">' +
                         '<h3>Pembayaran Berhasil!</h3>' +
                         '<p>Terima kasih, pembayaran Anda telah diterima. Anda akan diarahkan ke halaman konfirmasi pesanan.</p>' +
                         '</div>';
        
        $('.duitku-payment-info').prepend(successHtml);
        
        // Add success styling
        $('.duitku-payment-status.success').css({
            'background-color': '#d4edda',
            'border': '1px solid #c3e6cb',
            'color': '#155724',
            'padding': '15px',
            'border-radius': '5px',
            'margin-bottom': '15px'
        });
    }
    
    // Show payment cancelled message
    function showPaymentCancelled() {
        var cancelledHtml = '<div class="duitku-payment-status cancelled">' +
                           '<h3>Pembayaran Dibatalkan</h3>' +
                           '<p>Pembayaran Anda telah dibatalkan atau telah melewati batas waktu.</p>' +
                           '</div>';
        
        $('.duitku-payment-info').prepend(cancelledHtml);
        
        // Add cancelled styling
        $('.duitku-payment-status.cancelled').css({
            'background-color': '#f8d7da',
            'border': '1px solid #f5c6cb',
            'color': '#721c24',
            'padding': '15px',
            'border-radius': '5px',
            'margin-bottom': '15px'
        });
    }
    
    // Show payment timeout message
    function showPaymentTimeout() {
        var timeoutHtml = '<div class="duitku-payment-status timeout">' +
                         '<h3>Waktu Pengecekan Habis</h3>' +
                         '<p>Pengecekan otomatis telah dihentikan. Silakan refresh halaman untuk memeriksa status pembayaran terbaru.</p>' +
                         '<button type="button" class="button" onclick="location.reload()">Refresh Halaman</button>' +
                         '</div>';
        
        $('.duitku-payment-info').prepend(timeoutHtml);
        
        // Add timeout styling
        $('.duitku-payment-status.timeout').css({
            'background-color': '#fff3cd',
            'border': '1px solid #ffeaa7',
            'color': '#856404',
            'padding': '15px',
            'border-radius': '5px',
            'margin-bottom': '15px'
        });
    }
    
    // Update payment information
    function updatePaymentInfo(data) {
        if (data.va_number) {
            $('.duitku-va-number').text(data.va_number);
        }
        
        if (data.expiry_time) {
            $('.duitku-expiry-time').text(data.expiry_time);
        }
        
        // Update countdown timer if exists
        if (data.expiry_timestamp) {
            updateCountdown(data.expiry_timestamp);
        }
    }
    
    // Update countdown timer
    function updateCountdown(expiryTimestamp) {
        var countdownInterval = setInterval(function() {
            var now = new Date().getTime();
            var expiry = new Date(expiryTimestamp * 1000).getTime();
            var distance = expiry - now;
            
            if (distance < 0) {
                clearInterval(countdownInterval);
                $('.duitku-countdown').html('<span style="color: red;">Waktu pembayaran telah habis</span>');
                return;
            }
            
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            $('.duitku-countdown').html(
                '<strong>Sisa waktu: </strong>' +
                hours + ' jam ' + minutes + ' menit ' + seconds + ' detik'
            );
        }, 1000);
    }
    
    // Check if we're on order received page and should start checking
    if (typeof duitku_order_data !== 'undefined' && duitku_order_data.order_id) {
        startPaymentCheck(duitku_order_data.order_id);
    }
    
    // Handle manual refresh button
    $(document).on('click', '.duitku-refresh-status', function(e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');
        if (orderId) {
            checkPaymentStatus(orderId);
        }
    });
});
