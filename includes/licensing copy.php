<?php

function mpesa_gateway_license_activation_page() {
    add_menu_page(
        'License Activation',
        'License',
        'manage_options',
        'myplugin-license',
        'myplugin_license_activation_callback'
    );
}
add_action('admin_menu', 'mpesa_gateway_license_activation_page');

function myplugin_license_activation_callback() {
    ?>
    <div class="wrap">
        <h1>Activate Your License</h1>
        <form method="post" action="">
            <?php
            if ($_POST['license_key']) {
                $license_key = sanitize_text_field($_POST['license_key']);
                $response = wp_remote_post('https://your-licensing-server.com/api/validate-license', [
                    'body' => [
                        'license_key' => $license_key,
                        'domain' => site_url(),
                    ]
                ]);

                $response_body = wp_remote_retrieve_body($response);
                $result = json_decode($response_body, true);

                if ($result['status'] === 'success') {
                    update_option('myplugin_license_key', $license_key);
                    echo '<p style="color:green;">License activated successfully!</p>';
                } else {
                    echo '<p style="color:red;">' . esc_html($result['message']) . '</p>';
                }
            }
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="license_key">License Key</label></th>
                    <td><input name="license_key" type="text" id="license_key" value="" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button('Activate License'); ?>
        </form>
    </div>
    <?php
}

function myplugin_activate() {
    $license_key = get_option('myplugin_license_key');
    if (!$license_key) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Please activate your license first.');
    }
}
register_activation_hook(__FILE__, 'myplugin_activate');