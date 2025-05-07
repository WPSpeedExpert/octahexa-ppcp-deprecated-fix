# OctaHexa PPCP Deprecated Property Fix

A lightweight WordPress plugin that resolves deprecated property creation warnings and fatal errors in the AngellEYE PayPal for WooCommerce PPCP gateway. Prevents server log flooding, high CPU usage, and fatal errors on PHP 8.4+ environments.

## 🔧 Features

- Fixes fatal errors related to missing `WC_PPCP_Pre_Orders_Trait` in PayPal for WooCommerce
- Suppresses deprecated dynamic property warnings in FunnelKit/PayPal integration classes
- Provides an admin dashboard with detailed status information
- Works completely behind the scenes with no configuration required
- Does **not** modify the core PayPal plugin files (safe and update-proof)
- Fully GPLv3 and WordPress coding standards–compliant

## 🚀 Installation

1. Download the plugin as a ZIP file.
2. In your WordPress dashboard, go to **Plugins > Add New > Upload Plugin**.
3. Upload the ZIP file and click **Install Now**.
4. **IMPORTANT**: Activate this plugin BEFORE activating PayPal for WooCommerce.
5. Check the status page at **Settings > OctaHexa PPCP Fix** to verify everything is working.

## 📋 Requirements

- PHP 7.4 or higher (PHP 8.4+ supported)
- WordPress 5.6 or higher
- AngellEYE PayPal for WooCommerce plugin installed
- WooCommerce (optional but typical)

## ⚠️ Why This Plugin Exists

PHP 8.4 introduces stricter warnings about dynamic property creation, which can flood logs and cause high CPU usage. Additionally, a loading order issue in the PayPal plugin can cause fatal errors when a required trait isn't loaded early enough.

This plugin provides two critical fixes:
1. Ensures the PayPal trait is loaded early enough to prevent fatal errors
2. Suppresses deprecated property warnings without modifying the original plugin files

## 🧑‍💻 Developer Notes

- Fixes are applied efficiently using PHP's error handling mechanisms
- Plugin loads the required trait at the earliest possible hook (`muplugins_loaded`)
- Detailed status reporting in the admin dashboard
- Compatible with future PayPal plugin updates

## 📝 License

Licensed under the GNU General Public License v3.0.  
See: [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html)

## 👤 Author

**OctaHexa**  
Website: [https://octahexa.com](https://octahexa.com)  
GitHub: [WPSpeedExpert](https://github.com/WPSpeedExpert)

## 💬 Support

This is a lightweight compatibility patch.  
For issues with PayPal functionality itself, please contact [AngellEYE support](https://wordpress.org/plugins/paypal-for-woocommerce/).
