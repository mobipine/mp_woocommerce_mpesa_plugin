<?php

function myplugin_register_settings() {
    register_setting('myplugin_options', 'myplugin_license_key');
}

function myplugin_add_settings_page() {
    add_menu_page(
        'MPESA Plugin Details', // Page title
        'MPESA', // Menu title
        'manage_options', // Capability
        'myplugin-details', // Menu slug
        'myplugin_render_details_page', // Function to display the page content
        'dashicons-admin-network' // Dashicon class for payment
    );
}

function myplugin_render_details_page() {
    $license_key = get_option('myplugin_license_key');
    $validation_error = get_option('myplugin_license_validation_error');
    
    ?>
    <div class="wrap">
        <h2>My Plugin Details</h2>
        <?php if (empty($license_key) || $validation_error) : ?>
            <?php if ($validation_error) : ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($validation_error); ?></p>
                </div>
            <?php endif; ?>
            <p>Your plugin is not activated. Please enter your license key to activate it.</p>
            <form method="post" action="options.php">
                <?php settings_fields('myplugin_options'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">License Key</th>
                        <td>
                            <input type="text" name="myplugin_license_key" value="<?php echo esc_attr($license_key); ?>" style="width: 50%;">
                        </td>
                    </tr>
                </table>
                <?php submit_button('Activate Now'); ?>
            </form>
        <?php elseif(myplugin_validate_license()) : ?>
            <p>Your License Key is valid. Your plugin is activated.
            </p>
        <?php endif; ?>
    </div>
    <?php
}

function myplugin_validate_license($force_check = false) {
    $license_key = get_option('myplugin_license_key');
    $cached_validation = get_option('myplugin_license_validation');
    $validation_expiry = get_option('myplugin_license_validation_expiry');

    
    if (!$force_check && $cached_validation && $validation_expiry > time()) {

        if ($cached_validation === 'valid') {
            return true;
        } 
    }
    
    // error_log(print_r($license_key, true));
    $response = wp_remote_post('http://localhost:8000/api/validate-license', [
        'body' => [
            'license_key' => $license_key,
            'domain' => site_url(),
        ],
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    $is_valid = $response_body['status'] === 'success';

    if (!$is_valid) {
        update_option('myplugin_license_validation_error', 'Invalid license key. Please enter a valid key to activate this plugin.');
    } else {
        delete_option('myplugin_license_validation_error');
    }

    update_option('myplugin_license_validation', $is_valid ? 'valid' : 'invalid');
    update_option('myplugin_license_validation_expiry', time() + 12 * HOUR_IN_SECONDS); // every 12 hours

    return $is_valid;
}

add_action('admin_menu', 'myplugin_add_settings_page');
add_action('admin_init', 'myplugin_register_settings');
