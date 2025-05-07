<?php
/**
 * Plugin Name:       OctaHexa PPCP Deprecated Property Fix
 * Plugin URI:        https://github.com/WPSpeedExpert/octahexa-ppcp-deprecated-fix
 * Description:       Prevents high CPU usage and fatal errors from deprecated property creation in PayPal for WooCommerce plugin (PHP 8.4 compatibility).
 * Version:           1.2.0
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

// Plugin constants
define('OH_PPCP_FIX_VERSION', '1.2.0');
define('OH_PPCP_FIX_PLUGIN_FILE', __FILE__);

add_action('plugins_loaded', 'oh_patch_ppcp_deprecated_properties', 11);
add_action('admin_menu', 'oh_ppcp_fix_add_settings_page');
add_action('admin_enqueue_scripts', 'oh_ppcp_fix_admin_styles');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'oh_ppcp_fix_plugin_settings_link');

/**
 * Apply patch to PayPal classes if necessary and track status.
 */
function oh_patch_ppcp_deprecated_properties() {
    $status = [];
    $patched = false;
    
    // Check if PayPal plugin is active
    if (!defined('PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR')) {
        update_option('oh_ppcp_fix_status', 'paypal_plugin_not_active');
        return;
    }
    
    // List of properties that need to be added
    $props = [
        'api_log', 'payment_request', 'merchant_id', 'invoice_prefix',
        'landing_page', 'payee_preferred', 'set_billing_address'
    ];
    
    // Check WFOCU PayPal class
    if (class_exists('WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP') && 
        method_exists('WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP', 'get_instance')) {
        
        $instance = WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP::get_instance();
        
        $missing = array_filter($props, function ($prop) use ($instance) {
            return !property_exists($instance, $prop);
        });
        
        if (!empty($missing)) {
            foreach ($missing as $prop) {
                $instance->$prop = null;
            }
            $status[] = 'wfocu_class_patched:' . implode(',', $missing);
            $patched = true;
        } else {
            $status[] = 'wfocu_class_already_patched';
        }
    } else {
        $status[] = 'wfocu_class_not_found';
    }
    
    // Handle UpStroke Subscriptions class if it doesn't exist
    if (!class_exists('UpStroke_Subscriptions_AngellEYE_PPCP') && 
        class_exists('WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP')) {
        
        // Include our patch for UpStroke_Subscriptions class
        require_once plugin_dir_path(__FILE__) . 'includes/class-upstroke-subscriptions-angelleye-ppcp.php';
        $status[] = 'upstroke_class_added';
        $patched = true;
    } else {
        $status[] = 'upstroke_class_exists_or_not_needed';
    }
    
    // Update status and last run time
    update_option('oh_ppcp_fix_status', implode(';', $status));
    if ($patched) {
        update_option('oh_ppcp_fix_last_run', current_time('mysql'));
    }
}

/**
 * Add CSS styles for admin page
 */
function oh_ppcp_fix_admin_styles($hook) {
    if ('settings_page_octahexa-ppcp-fix' !== $hook) {
        return;
    }
    
    wp_enqueue_style('oh-ppcp-fix-admin', plugin_dir_url(__FILE__) . 'assets/admin-style.css', [], OH_PPCP_FIX_VERSION);
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
 * Render settings page with human-readable status information.
 */
function oh_ppcp_fix_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $status = get_option('oh_ppcp_fix_status', 'not_run');
    $last_run = get_option('oh_ppcp_fix_last_run', 'never');
    $status_parts = explode(';', $status);
    
    $translation = [
        'not_run' => 'Plugin has not run yet',
        'paypal_plugin_not_active' => 'PayPal for WooCommerce plugin is not active',
        'wfocu_class_not_found' => 'WFOCU PayPal class not found (might not be necessary for your setup)',
        'wfocu_class_patched' => 'Successfully patched WFOCU PayPal class',
        'wfocu_class_already_patched' => 'WFOCU PayPal class already has all required properties',
        'upstroke_class_added' => 'Added missing UpStroke Subscriptions PayPal class',
        'upstroke_class_exists_or_not_needed' => 'UpStroke Subscriptions class exists or is not needed'
    ];

    // Start output
    echo '<div class="wrap oh-ppcp-fix-wrap">';
    echo '<h1>OctaHexa PPCP Fix Status</h1>';
    
    // Display information box
    echo '<div class="oh-ppcp-info-box">';
    echo '<h2>Plugin Information</h2>';
    echo '<p>This plugin fixes PHP 8.4 deprecated property warnings in PayPal for WooCommerce plugin by pre-defining the properties that would otherwise be created dynamically, which can cause high CPU usage and server load.</p>';
    echo '</div>';
    
    // Status overview
    echo '<h2>Current Status</h2>';
    echo '<table class="widefat striped" style="max-width:800px;">';
    echo '<thead><tr><th>Check</th><th>Result</th><th>Status</th></tr></thead><tbody>';

    // PayPal plugin status
    echo '<tr><td>PayPal Plugin Active</td><td>' . (defined('PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR') ? '✅ Yes' : '❌ No') . '</td>';
    echo '<td>' . (defined('PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR') ? 'PayPal for WooCommerce detected' : 'PayPal for WooCommerce not active or installed') . '</td></tr>';
    
    // WFOCU class status
    echo '<tr><td>WFOCU PayPal Class</td><td>' . (class_exists('WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP') ? '✅ Found' : '❓ Not Found') . '</td>';
    echo '<td>' . (class_exists('WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP') ? 'WFOCU PayPal integration is active' : 'WFOCU PayPal integration not active (not an issue if you don\'t use it)') . '</td></tr>';
    
    // UpStroke class status
    echo '<tr><td>UpStroke Subscriptions Class</td><td>' . (class_exists('UpStroke_Subscriptions_AngellEYE_PPCP') ? '✅ Found' : '❓ Not Found') . '</td>';
    echo '<td>' . (class_exists('UpStroke_Subscriptions_AngellEYE_PPCP') ? 'UpStroke Subscriptions class is available' : 'UpStroke Subscriptions class not loaded (not an issue if you don\'t use subscriptions)') . '</td></tr>';
    
    // Status details
    echo '<tr><td>Fix Status</td><td colspan="2"><ul>';
    foreach ($status_parts as $part) {
        $status_key = preg_replace('/:.*$/', '', $part);
        $message = isset($translation[$status_key]) ? $translation[$status_key] : $part;
        
        if (strpos($part, ':') !== false) {
            list($status_code, $details) = explode(':', $part, 2);
            if ($status_code === 'wfocu_class_patched') {
                $message = 'Successfully patched WFOCU PayPal class with properties: <code>' . esc_html($details) . '</code>';
            }
        }
        
        echo '<li>' . $message . '</li>';
    }
    echo '</ul></td></tr>';
    
    // Last run
    echo '<tr><td>Last Successful Patch</td><td colspan="2">' . esc_html($last_run) . '</td></tr>';
    
    echo '</tbody></table>';
    
    // PHP Version check
    $php_version = phpversion();
    $php_compatible = version_compare($php_version, '8.0', '>=');
    
    echo '<h2>Environment</h2>';
    echo '<table class="widefat striped" style="max-width:800px;">';
    echo '<tr><td>PHP Version</td><td>' . esc_html($php_version) . '</td>';
    echo '<td>' . ($php_compatible ? '✅ Compatible' : '⚠️ This plugin is designed for PHP 8.0+') . '</td></tr>';
    echo '</table>';
    
    // Debug information (for support)
    echo '<h2>Debug Information</h2>';
    echo '<p>If you need support, please include the following information:</p>';
    echo '<pre>';
    echo 'Plugin Version: ' . OH_PPCP_FIX_VERSION . "\n";
    echo 'PHP Version: ' . $php_version . "\n";
    echo 'WordPress Version: ' . get_bloginfo('version') . "\n";
    echo 'Status: ' . esc_html($status) . "\n";
    echo 'Last Run: ' . esc_html($last_run) . "\n";
    if (defined('PAYPAL_FOR_WOOCOMMERCE_VERSION')) {
        echo 'PayPal For WooCommerce Version: ' . PAYPAL_FOR_WOOCOMMERCE_VERSION . "\n";
    }
    echo '</pre>';
    
    echo '</div>'; // End wrap
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
