<?php
/**
 * Plugin Name:       OctaHexa PPCP Deprecated Property Fix
 * Plugin URI:        https://github.com/WPSpeedExpert/octahexa-ppcp-deprecated-fix
 * Description:       Prevents high CPU usage and fatal errors in PayPal for WooCommerce plugin (PHP 8.4 compatibility).
 * Version:           1.7.0
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
define('OH_PPCP_FIX_VERSION', '1.7.0');
define('OH_PPCP_FIX_PLUGIN_FILE', __FILE__);

// Make our load action run very early
add_action('plugins_loaded', 'oh_load_ppcp_trait_file', 5); // Priority 5 ensures it runs early
add_action('plugins_loaded', 'oh_patch_ppcp_deprecated_properties', 20); 
add_action('admin_menu', 'oh_ppcp_fix_add_settings_page');
add_action('admin_enqueue_scripts', 'oh_ppcp_fix_admin_styles');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'oh_ppcp_fix_plugin_settings_link');

/**
 * Pre-load the PayPal trait file before PayPal plugin tries to use it
 */
function oh_load_ppcp_trait_file() {
    // Check if the trait already exists (no need to load it again)
    if (trait_exists('WC_PPCP_Pre_Orders_Trait')) {
        update_option('oh_ppcp_fix_trait_status', 'trait_already_exists');
        return;
    }
    
    // PayPal plugin must be active
    if (!defined('PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR') || !file_exists(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR)) {
        update_option('oh_ppcp_fix_trait_status', 'paypal_plugin_not_active');
        return;
    }
    
    // Find and load the trait file from PayPal plugin
    $trait_path = PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/pre-order/trait-wc-ppcp-pre-orders.php';
    
    if (file_exists($trait_path)) {
        // Load the original trait file from PayPal plugin
        require_once $trait_path;
        update_option('oh_ppcp_fix_trait_status', 'trait_loaded_from_paypal');
    } else {
        update_option('oh_ppcp_fix_trait_status', 'trait_file_missing');
    }
}

/**
 * Apply patch to PayPal classes to fix deprecated property warnings.
 */
function oh_patch_ppcp_deprecated_properties() {
    $status = [];
    $patched = false;
    
    // Check if PayPal plugin is active
    if (!defined('PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR') || !file_exists(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR)) {
        update_option('oh_ppcp_fix_status', 'paypal_plugin_not_active');
        return;
    }
    
    // Add trait status
    $trait_status = get_option('oh_ppcp_fix_trait_status', 'unknown');
    $status[] = 'trait_status:' . $trait_status;
    
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
    $trait_status = get_option('oh_ppcp_fix_trait_status', 'unknown');
    $last_run = get_option('oh_ppcp_fix_last_run', 'never');
    $status_parts = explode(';', $status);
    
    $translation = [
        'not_run' => 'Plugin has not run yet',
        'paypal_plugin_not_active' => 'PayPal for WooCommerce plugin is not active',
        'trait_status:trait_loaded_from_paypal' => 'Successfully loaded WC_PPCP_Pre_Orders_Trait from PayPal plugin',
        'trait_status:trait_already_exists' => 'WC_PPCP_Pre_Orders_Trait already exists',
        'trait_status:trait_file_missing' => 'WARNING: WC_PPCP_Pre_Orders_Trait file missing from PayPal plugin',
        'trait_status:paypal_plugin_not_active' => 'PayPal for WooCommerce plugin not active',
        'trait_status:unknown' => 'WC_PPCP_Pre_Orders_Trait status unknown',
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
    echo '<p>This plugin fixes PHP 8.4 compatibility issues in PayPal for WooCommerce plugin by:</p>';
    echo '<ul>';
    echo '<li>Ensuring the original WC_PPCP_Pre_Orders_Trait from PayPal is loaded early enough (prevents fatal errors)</li>';
    echo '<li>Pre-defining properties that would otherwise be created dynamically (prevents deprecated notices and high CPU usage)</li>';
    echo '</ul>';
    echo '</div>';
    
    // Table of Contents
    echo '<div class="oh-ppcp-toc">';
    echo '<h2>Table of Contents</h2>';
    echo '<ul>';
    echo '<li><a href="#status">Current Status</a></li>';
    echo '<li><a href="#environment">Environment</a></li>';
    echo '<li><a href="#troubleshooting">Troubleshooting</a></li>';
    echo '<li><a href="#debug">Debug Information</a></li>';
    echo '</ul>';
    echo '</div>';
    
    // Status overview
    echo '<h2 id="status">Current Status</h2>';
    echo '<table class="widefat striped" style="max-width:800px;">';
    echo '<thead><tr><th>Check</th><th>Result</th><th>Status</th></tr></thead><tbody>';

    // PayPal plugin status
    $paypal_active = defined('PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR') && file_exists(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR);
    echo '<tr><td>PayPal Plugin Active</td><td>' . ($paypal_active ? '✅ Yes' : '❌ No') . '</td>';
    echo '<td>' . ($paypal_active ? 'PayPal for WooCommerce detected' : 'PayPal for WooCommerce not active or installed') . '</td></tr>';
    
    // Trait status
    echo '<tr><td>WC_PPCP_Pre_Orders_Trait</td><td>' . (trait_exists('WC_PPCP_Pre_Orders_Trait') ? '✅ Found' : '❌ Missing') . '</td>';
    $trait_desc = '';
    if ($trait_status === 'trait_loaded_from_paypal') {
        $trait_desc = 'Successfully loaded from PayPal plugin';
    } else if ($trait_status === 'trait_already_exists') {
        $trait_desc = 'Already defined by PayPal plugin or another source';
    } else if ($trait_status === 'trait_file_missing') {
        $trait_desc = 'WARNING: Trait file missing from PayPal plugin - update PayPal plugin';
    } else if ($trait_status === 'paypal_plugin_not_active') {
        $trait_desc = 'PayPal for WooCommerce plugin not active';
    } else {
        $trait_desc = 'Unknown status';
    }
    echo '<td>' . $trait_desc . '</td></tr>';
    
    // WFOCU class status
    echo '<tr><td>WFOCU PayPal Class</td><td>' . (class_exists('WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP') ? '✅ Found' : '❓ Not Found') . '</td>';
    echo '<td>' . (class_exists('WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP') ? 'WFOCU PayPal integration is active' : 'WFOCU PayPal integration not active (not an issue if you don\'t use it)') . '</td></tr>';
    
    // UpStroke class status
    echo '<tr><td>UpStroke Subscriptions Class</td><td>' . (class_exists('UpStroke_Subscriptions_AngellEYE_PPCP') ? '✅ Found' : '❓ Not Found') . '</td>';
    echo '<td>' . (class_exists('UpStroke_Subscriptions_AngellEYE_PPCP') ? 'UpStroke Subscriptions class is available' : 'UpStroke Subscriptions class not loaded (not an issue if you don\'t use subscriptions)') . '</td></tr>';
    
    // Deprecated property fixing status
    $deprecated_warning = false;
    if (class_exists('WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP')) {
        $instance = WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP::get_instance();
        $props = ['api_log', 'payment_request', 'merchant_id', 'invoice_prefix', 
                 'landing_page', 'payee_preferred', 'set_billing_address'];
        $missing = array_filter($props, function ($prop) use ($instance) {
            return !property_exists($instance, $prop);
        });
        $deprecated_warning = !empty($missing);
    }
    
    echo '<tr><td>Deprecated Properties</td><td>' . ($deprecated_warning ? '⚠️ Issues' : '✅ Fixed') . '</td>';
    echo '<td>' . ($deprecated_warning ? 'Some properties are still missing - plugin may need updating' : 'All necessary properties are properly defined') . '</td></tr>';
    
    // Status details
    echo '<tr><td>Fix Status</td><td colspan="2"><ul>';
    foreach ($status_parts as $part) {
        $message = isset($translation[$part]) ? $translation[$part] : $part;
        
        if (strpos($part, ':') !== false && !isset($translation[$part])) {
            list($status_code, $details) = explode(':', $part, 2);
            if ($status_code === 'wfocu_class_patched') {
                $message = 'Successfully patched WFOCU PayPal class with properties: <code>' . esc_html($details) . '</code>';
            } else if ($status_code === 'trait_status') {
                $message = isset($translation[$status_code . ':' . $details]) ? 
                    $translation[$status_code . ':' . $details] : 
                    'Trait status: ' . $details;
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
    
    echo '<h2 id="environment">Environment</h2>';
    echo '<table class="widefat striped" style="max-width:800px;">';
    echo '<tr><td>PHP Version</td><td>' . esc_html($php_version) . '</td>';
    echo '<td>' . ($php_compatible ? '✅ Compatible' : '⚠️ This plugin is designed for PHP 8.0+') . '</td></tr>';
    echo '</table>';
    
    // Troubleshooting information
    echo '<h2 id="troubleshooting">Troubleshooting</h2>';
    echo '<p>If you continue to experience issues:</p>';
    echo '<ol>';
    echo '<li>Make sure you are using the latest version of PayPal for WooCommerce plugin</li>';
    echo '<li>Try these steps in order:
           <ul>
           <li>Deactivate both PayPal for WooCommerce and this fix plugin</li>
           <li>Activate this fix plugin first</li>
           <li>Then activate PayPal for WooCommerce</li>
           </ul>
        </li>';
    echo '<li>Check if any PHP errors persist in your server\'s error log</li>';
    echo '<li>If issues continue, contact the PayPal for WooCommerce plugin developers regarding PHP 8.4 compatibility</li>';
    echo '</ol>';
    
    // Debug information (for support)
    echo '<h2 id="debug">Debug Information</h2>';
    echo '<p>If you need support, please include the following information:</p>';
    echo '<pre>';
    echo 'Plugin Version: ' . OH_PPCP_FIX_VERSION . "\n";
    echo 'PHP Version: ' . $php_version . "\n";
    echo 'WordPress Version: ' . get_bloginfo('version') . "\n";
    echo 'Status: ' . esc_html($status) . "\n";
    echo 'Trait Status: ' . esc_html($trait_status) . "\n";
    echo 'Last Run: ' . esc_html($last_run) . "\n";
    echo 'Trait Exists: ' . (trait_exists('WC_PPCP_Pre_Orders_Trait') ? 'Yes' : 'No') . "\n";
    
    // PayPal plugin version
    if (defined('PAYPAL_FOR_WOOCOMMERCE_VERSION')) {
        echo 'PayPal For WooCommerce Version: ' . PAYPAL_FOR_WOOCOMMERCE_VERSION . "\n";
    }
    
    // Trait file existence check
    if (defined('PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR')) {
        $trait_path = PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/pre-order/trait-wc-ppcp-pre-orders.php';
        echo 'Trait File Path: ' . $trait_path . "\n";
        echo 'Trait File Exists: ' . (file_exists($trait_path) ? 'Yes' : 'No') . "\n";
        
        // Check for the class file that's causing the error
        $class_path = PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-angelleye-paypal-ppcp-payment.php';
        echo 'PPCP Payment Class Path: ' . $class_path . "\n";
        echo 'PPCP Payment Class Exists: ' . (file_exists($class_path) ? 'Yes' : 'No') . "\n";
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
