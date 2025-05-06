<?php
/**
 * Plugin Name:       OctaHexa PPCP Deprecated Property Fix
 * Plugin URI:        https://octahexa.com/plugins/octahexa-ppcp-deprecated-fix
 * Description:       Prevents high CPU usage and fatal errors from deprecated property creation and missing trait usage in the AngellEYE PayPal plugin.
 * Version:           1.1.0
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
add_action('admin_menu', 'oh_ppcp_fix_add_settings_page');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'oh_ppcp_fix_plugin_settings_link');

/**
 * Apply patch if necessary and track status.
 */
function oh_patch_ppcp_deprecated_properties() {
    if (!trait_exists('WC_PPCP_Pre_Orders_Trait') && defined('PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR')) {
        $trait_file = PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/traits/class-wc-ppcp-pre-orders-trait.php';
        if (file_exists($trait_file)) {
            require_once $trait_file;
        } else {
            update_option('oh_ppcp_fix_status', 'trait_missing');
            return;
        }
    }

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
    update_option('oh_ppcp_fix_last_run', current_time('mysql'));
}

/**
 * Add admin settings page under Settings.
 */
function oh_ppcp_fix_add_settings_page() {
    add_options_page(
        'OctaHexa PPCP Fix Status',
        'OctaHexa PPCP Fix',
        'manage_options',
        'octahexa-ppcp-fix',
        'oh_ppcp_fix_settings_page_html'
    );
}

/**
 * Render settings page.
 */
function oh_ppcp_fix_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $status = get_option('oh_ppcp_fix_status');
    $last_run = get_option('oh_ppcp_fix_last_run');

    echo '<div class="wrap"><h1>OctaHexa PPCP Fix Status</h1>';
    echo '<table class="widefat striped" style="max-width:600px;">';
    echo '<thead><tr><th>Check</th><th>Result</th></tr></thead><tbody>';

    echo '<tr><td>Trait Loaded</td><td>' . (trait_exists('WC_PPCP_Pre_Orders_Trait') ? '✅ Yes' : '❌ No') . '</td></tr>';
    echo '<tr><td>Class Loaded</td><td>' . (class_exists('WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP') ? '✅ Yes' : '❌ No') . '</td></tr>';
    echo '<tr><td>Status</td><td><code>' . esc_html($status ?: 'unknown') . '</code></td></tr>';
    echo '<tr><td>Last Patch Run</td><td>' . esc_html($last_run ?: 'never') . '</td></tr>';

    echo '</tbody></table>';
    echo '</div>';
}

/**
 * Add settings link in plugin listing.
 */
function oh_ppcp_fix_plugin_settings_link($links) {
    $settings_url = admin_url('options-general.php?page=octahexa-ppcp-fix');
    $settings_link = '<a href="' . esc_url($settings_url) . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
