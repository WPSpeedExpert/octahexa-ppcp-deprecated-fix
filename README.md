# OctaHexa PPCP Deprecated Property Fix

A lightweight WordPress plugin that resolves deprecated dynamic property creation issues in the AngellEYE PayPal for WooCommerce PPCP gateway. Prevents server log flooding and high CPU usage on PHP 8.2+ environments.

---

## 🔧 Features

- Patches deprecated dynamic property usage in the `WFOCU_Paypal_For_WC_Gateway_AngellEYE_PPCP` class.
- Applies only if necessary and only once per request.
- Adds no settings pages — just activate and forget.
- Shows an admin notice on the dashboard for transparency.
- Does **not** modify the core PayPal plugin (safe and update-proof).
- Fully GPLv3 and WordPress coding standards–compliant.

---

## 🚀 Installation

1. Download the plugin as a ZIP file.
2. In your WordPress dashboard, go to **Plugins > Add New > Upload Plugin**.
3. Upload the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.
5. If the patch is applied or skipped, a notice will appear in the admin area.

---

## 📋 Requirements

- PHP 7.4 or higher (PHP 8.2+ recommended)
- WordPress 5.6 or higher
- AngellEYE PayPal for WooCommerce plugin installed and active
- WooCommerce (optional but typical)

---

## ⚠️ Why This Plugin Exists

Starting with PHP 8.2, creating properties dynamically (i.e., without declaring them first) triggers a `Deprecated` warning. The AngellEYE PayPal plugin still uses this outdated pattern, which can flood logs and overload servers.

This plugin solves that without editing the original plugin — making it safe and maintainable.

---

## 🧑‍💻 Developer Notes

- Patch only runs if the class exists.
- Patch applies only to missing properties.
- Patch is stored with status in `oh_ppcp_fix_status` option.
- Admin notices are shown only to users with `manage_options`.

---

## 📝 License

Licensed under the GNU General Public License v3.0.  
See: [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html)

---

## 👤 Author

**OctaHexa**  
Website: [https://octahexa.com](https://octahexa.com)  
GitHub: [WPSpeedExpert](https://github.com/WPSpeedExpert)

---

## 💬 Support

This is a lightweight compatibility patch.  
For issues with PayPal functionality itself, please contact [AngellEYE support](https://wordpress.org/plugins/paypal-for-woocommerce/).
