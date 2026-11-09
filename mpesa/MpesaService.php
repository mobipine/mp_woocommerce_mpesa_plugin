<?php

class MpesaService
{

    function stk_push($phone, $amount, $order_id, $credentials, $callbackUrl, $env)
    {

        $consumerKey = $credentials['consumerKey']; // Replace with your consumer key
        $consumerSecret = $credentials['consumerSecret']; // Replace with your consumer secret
        $shortCode = $credentials['shortCode']; // Test Shortcode (use your own for production)
        $passkey = $credentials['passkey']; // From Safaricom portal
        $accountReference = $credentials['accountReference'] == '' ? "$order_id" : $credentials['accountReference']; // This is anything that helps identify the specific transaction       
        // $callbackUrl = 'https://76bd-102-68-76-239.ngrok-free.app/yobazar/index.php/wc-api/callback';


        //get the first char of the phone which would be a 0 and replace it with 254
        $phone = preg_replace('/^0/', '254', $phone);


        // Ensure phone is in the correct format
        if (!preg_match('/^2547\d{8}$/', $phone) && !preg_match('/^2541\d{8}$/', $phone)) {
            return json_encode(array('errorCode' => 'INVALID_PHONE', 'errorMessage' => 'Invalid phone number format.'));
        }

        $accessToken = $this->getAccessToken($consumerKey, $consumerSecret, $env);
        $password = $this->generatePassword($shortCode, $passkey);
        $timestamp = date('YmdHis');

        // Prepare the STK Push Request Payload
        $data = array(
            'BusinessShortCode' => $shortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone, //this is the sender's phone number
            'PartyB' => $shortCode, //this is the paybill number of the receiver
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => "Payment for order $order_id"
            //pass the order id where it can be accessed in the callback

        );

        

        //Send STK Push Request
        $curl = curl_init();
        
        // Select the appropriate URL based on environment
        $url = ($env == 'sandbox') 
            ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
            
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $accessToken));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($curl);
        
        if (curl_errno($curl)) {
            $error = curl_error($curl);
            curl_close($curl);
            // Provider connection failures are surfaced to WooCommerce without logging request data.
            // Return an error response in the expected format
            return json_encode(array(
                'errorCode' => 'CURL_ERROR',
                'errorMessage' => $error
            ));
        }
        
        curl_close($curl);

        // Parse and validate the response from Safaricom API
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Do not log raw payment provider payloads or access token responses.
            return json_encode(array(
                'errorCode' => 'INVALID_JSON',
                'errorMessage' => 'Invalid response from M-Pesa API'
            ));
        }
        
        // Check if the request was successful
        if (isset($result['MerchantRequestID']) && isset($result['CheckoutRequestID'])) {
            $merchantRequestID = $result['MerchantRequestID'];
            $checkoutRequestID = $result['CheckoutRequestID'];
            
            // Save merchant request ID to order meta
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_meta_data('_merchant_request_id', $merchantRequestID);
                $order->update_meta_data('_checkout_request_id', $checkoutRequestID);
                $order->add_order_note(__('M-Pesa payment request submitted to the provider.', 'woocommerce'));
                $order->save();
                

            }
        } else {
            // Do not log raw payment provider payloads or access token responses.
        }

        return $response;
    }

    function getAccessToken($consumerKey, $consumerSecret, $env = 'live') {
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
        $curl = curl_init();
        
        // Select the appropriate URL based on environment
        $url = ($env == 'sandbox') 
            ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
            
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($curl);
        
        if (curl_errno($curl)) {
            $error = curl_error($curl);
            curl_close($curl);
            // Provider connection failures are surfaced to WooCommerce without logging request data.
            throw new Exception("Failed to get access token: " . $error);
        }
        
        curl_close($curl);
        
        $result = json_decode($response, true);
        
        if (!isset($result['access_token'])) {
            // Do not log raw payment provider payloads or access token responses.
            throw new Exception("Failed to get access token from M-Pesa API");
        }
        
        return $result['access_token'];
    }
    
    // Generate the Password for the STK Push
    function generatePassword($shortCode, $passkey) {
        $timestamp = date('YmdHis');
        return base64_encode($shortCode . $passkey . $timestamp);
    }
    
    // Query Transaction Status (for STK Push)
    function query_transaction_status($checkoutRequestID, $credentials, $env)
    {
        $consumerKey = $credentials['consumerKey'];
        $consumerSecret = $credentials['consumerSecret'];
        $shortCode = $credentials['shortCode'];
        $passkey = $credentials['passkey'];
        
        // Get access token
        $accessToken = $this->getAccessToken($consumerKey, $consumerSecret, $env);
        
        // Generate password and timestamp
        $timestamp = date('YmdHis');
        $password = base64_encode($shortCode . $passkey . $timestamp);
        
        // Prepare request data
        $data = array(
            'BusinessShortCode' => $shortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestID
        );
        
            // Do not log raw payment provider payloads or access token responses.
        
        // Select endpoint based on environment
        $url = ($env == 'sandbox') 
            ? 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query'
            : 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query';
        
        // Make API call
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($curl);
        
        if (curl_errno($curl)) {
            $error = curl_error($curl);
            curl_close($curl);
            // Provider connection failures are surfaced to WooCommerce without logging request data.
            return json_encode(array(
                'errorCode' => 'CURL_ERROR',
                'errorMessage' => $error
            ));
        }
        
        curl_close($curl);
        
            // Do not log raw payment provider payloads or access token responses.
        
        return $response;
    }
}
