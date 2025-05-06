<?php
/**
 * Plugin Name:       OctaHexa PPCP Deprecated Property Fix
 * Plugin URI:        https://octahexa.com/plugins/octahexa-ppcp-deprecated-fix
 * Description:       Prevents high CPU usage and fatal errors from deprecated property creation and missing trait usage in the AngellEYE PayPal plugin.
 * Version:           1.0.5
 * Author:            OctaHexa
 * Author URI:        https://octahexa.com
 * Text Domain:       octahexa-ppcp-fix
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 5.6
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * GitHub Plugin URI: https://github.com/WPSpeedExpert/octahexa-ppcp-deprecated-fix
 * Primary Branch:    main
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'oh_patch_ppcp_deprecated_properties', 11);

function oh_patch_ppcp_deprecated_properties() {
    // Prevent fatal error if trait used by the PayPal plugin is not loaded
    if (!trait_exists('WC_PPCP_Pre_Orders_Trait') && defined('PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR')) {
        $trait_file = PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/traits/class-wc-ppcp-pre-orders-trait.php';
        if (file_exists($trait_file)) {
            require_once $trait_file;
        } else {
            update_option('oh_ppcp_fix_status', 'trait_missing');
            return;
        }
    }

    // Prevent fatal error if the PayPal class is not loaded or missing its method
    if (!class_exists('WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP') || !method_exists('WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP', 'get_instance')) {
        update_option('oh_ppcp_fix_status', 'class_or_method_missing');
        return;
    }

    $instance = WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP::get_instance();

    $props = [
        'api_log', 'payment_request', 'merchant_id', 'invoice_prefix',
        'landing_page', 'payee_preferred', 'set_billing_address'
    ];

    $missing = array_filter($props, function ($prop) use ($instance) {
        return !property_exists($instance, $prop);
    });

    if (empty($missing)) {
        update_option('oh_ppcp_fix_status', 'already_patched');
        return;
    }

    foreach ($missing as $prop) {
        $instance->$prop = null;
    }

    update_option('oh_ppcp_fix_status', 'patched:' . implode(',', $missing));
}

add_action('admin_notices', 'oh_ppcp_admin_notice');

function oh_ppcp_admin_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $status = get_option('oh_ppcp_fix_status');

    if ($status === 'trait_missing') {
        echo '<div class="notice notice-error is-dismissible"><p><strong>OctaHexa PPCP Fix:</strong> Required trait <code>WC_PPCP_Pre_Orders_Trait</code> could not be loaded. File is missing.</p></div>';
    } elseif ($status === 'class_or_method_missing') {
        echo '<div class="notice notice-warning is-dismissible"><p><strong>OctaHexa PPCP Fix:</strong> PayPal class or method not found. Plugin may be inactive or loading too late.</p></div>';
    } elseif ($status === 'already_patched') {
        echo '<div class="notice notice-success is-dismissible"><p><strong>OctaHexa PPCP Fix:</strong> No deprecated properties found — patch not required.</p></div>';
    } elseif (strpos($status, 'patched:') === 0) {
        $props = str_replace('patched:', '', $status);
        echo '<div class="notice notice-info is-dismissible"><p><strong>OctaHexa PPCP Fix:</strong> Patch applied to deprecated properties: <code>' . esc_html($props) . '</code>.</p></div>';
    }
}
