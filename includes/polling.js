document.addEventListener('DOMContentLoaded', function () {
    var order_id = document.getElementById('order_id').value;
    var ajaxurl = homeUrl + '/index.php/wc-api/redirects';
    var maxRetries = 12; // Limit to 12 retries (1 minute)
    var retryCount = 0;

    // Check local storage for order status
    var orderStatus = localStorage.getItem('order_status_' + order_id);

    function hideAwaitingShowOneState(showSuccess, showFailed) {
        var awaitingEl = document.getElementById('awaiting-message');
        if (awaitingEl) awaitingEl.classList.add('hidden');
        var spinnerEl = document.getElementById('spinner');
        if (spinnerEl) spinnerEl.classList.add('hidden');
        var successEl = document.getElementById('success-message');
        var failedEl = document.getElementById('failed-message');
        if (showSuccess) {
            if (successEl) successEl.classList.remove('hidden');
            if (failedEl) failedEl.classList.add('hidden');
        } else if (showFailed) {
            if (failedEl) failedEl.classList.remove('hidden');
            if (successEl) successEl.classList.add('hidden');
        }
    }

    if (orderStatus === 'completed') {
        hideAwaitingShowOneState(true, false);
        var viewOrderLink = document.getElementById('view-order-link');
        if (viewOrderLink) {
            viewOrderLink.classList.remove('hidden');
            viewOrderLink.href = homeUrl + '/my-account/view-order/' + order_id;
        }
    } else if (orderStatus === 'failed') { 
        hideAwaitingShowOneState(false, true);
    } else {
        // Start polling if the order is not completed
        checkOrderStatus();
    }

    function checkOrderStatus() {
        fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: JSON.stringify({
                    order_id: order_id
                }),
            })
            .then(response => response.json())
            .then(data => {
                console.log("Status: " + data.data.status);
                if (data.data.status == 'completed') {
                    console.log('Order status: completed' + order_id);
                    console.log("status: " + data.data.status);

                    hideAwaitingShowOneState(true, false);
                    var viewOrderLink = document.getElementById('view-order-link');
                    if (viewOrderLink) {
                        viewOrderLink.href = homeUrl + '/my-account/view-order/' + order_id;
                        viewOrderLink.classList.remove('hidden');
                    }

                    // Save the order status in local storage
                    localStorage.setItem('order_status_' + order_id, 'completed');
                } else if (data.data.status == 'failed') {
                    console.log('Payment failed.', data);
                    localStorage.setItem('order_status_' + order_id, 'pending');

                    hideAwaitingShowOneState(false, true);
                    // Stop polling
                    retryCount = maxRetries;

                    //get the redirect url
                    var redirectUrl = data.data.redirect;
                    console.log('Redirecting to: ' + redirectUrl);
                    
                    //redirect to the url
                    window.location.href = redirectUrl;
                    



                        

                } else if (retryCount < maxRetries) {
                    // Keep showing spinner and polling
                    var spinnerEl = document.getElementById('spinner');
                    if (spinnerEl) spinnerEl.classList.remove('hidden');
                    retryCount++;
                    pollOrderStatus();
                    console.log('Polling order ' + order_id + ' status...');
                } else {
                    // Max retries reached
                    console.log('Max retries reached. Stopping polling.');
                    localStorage.setItem('order_status_' + order_id, 'pending');
                    hideAwaitingShowOneState(false, true);
                }
            })
            .catch(error => {
                console.error('AJAX request failed:', error);
            });
    }

    function pollOrderStatus() {
        setTimeout(checkOrderStatus, 5000); // Poll every 5 seconds
    }

    // Handle retry button click
    // document.getElementById('retry-button').addEventListener('click', function () {
    //     reinitiatePayment(order_id);
    // });

    function reinitiatePayment(order_id) {
        fetch(homeUrl + '/index.php/wc-api/reinitiate_payment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: JSON.stringify({
                    order_id: order_id
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reset local storage and retry count
                    localStorage.removeItem('order_status_' + order_id);
                    retryCount = 0;

                    // Hide failed message and start polling again
                    document.getElementById('failed-message').classList.add('hidden');
                    checkOrderStatus();
                } else {
                    console.error('Failed to reinitiate payment:', data.message);
                }
            })
            .catch(error => {
                console.error('AJAX request failed:', error);
            });
    }
});
