document.addEventListener('DOMContentLoaded', function () {
    var order_id = document.getElementById('order_id').value;
    var ajaxurl = homeUrl + '/index.php/wc-api/redirects';
    var maxRetries = 12; // Limit to 12 retries (1 minute)
    var retryCount = 0;

    // Check local storage for order status
    var orderStatus = localStorage.getItem('order_status_' + order_id);

    if (orderStatus === 'completed') {
        // Show the success message if the order is already completed
        document.getElementById('success-message').classList.remove('hidden');
        document.getElementById('spinner').classList.add('hidden');
        document.getElementById('view-order-link').classList.remove('hidden');
        document.getElementById('view-order-link').href = homeUrl + '/my-account/view-order/' + order_id;
    } else if (orderStatus === 'failed') { 
        // Show the failed message if the order has failed
        document.getElementById('failed-message').classList.remove('hidden');
        document.getElementById('spinner').classList.add('hidden');
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

                    // Show the success message
                    document.getElementById('success-message').classList.remove('hidden');
                    document.getElementById('spinner').classList.add('hidden');

                    // Create and append the order link
                    var viewOrderLink = document.getElementById('view-order-link');
                    viewOrderLink.href = homeUrl + '/my-account/view-order/' + order_id;
                    viewOrderLink.classList.remove('hidden');

                    // Save the order status in local storage
                    localStorage.setItem('order_status_' + order_id, 'completed');
                } else if (data.data.status == 'failed') {
                    console.log('Payment Pending.', data);
                    localStorage.setItem('order_status_' + order_id, 'pending');

                    document.getElementById('spinner').classList.add('hidden');
                    document.getElementById('failed-message').classList.remove('hidden');
                    // Stop polling
                    retryCount = maxRetries;

                    //get the redirect url
                    var redirectUrl = data.data.redirect;
                    console.log('Redirecting to: ' + redirectUrl);
                    
                    //redirect to the url
                    window.location.href = redirectUrl;
                    



                        

                } else if (retryCount < maxRetries) {
                    // Show the spinner and start polling
                    document.getElementById('spinner').classList.remove('hidden');
                    retryCount++;
                    pollOrderStatus();
                    console.log('Pollingggg order' + order_id + ' status...');
                } else {
                    //this means that the max retries have been reached
                    console.log('Max retries reached. Stopping polling.');
                    localStorage.setItem('order_status_' + order_id, 'pending');

                    document.getElementById('spinner').classList.add('hidden');
                    document.getElementById('failed-message').classList.remove('hidden');
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
