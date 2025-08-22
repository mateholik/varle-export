=== Varle.lt Product Export ===
Contributors: yourname
Tags: woocommerce, export, varle, lithuania, xml
Requires at least: 5.0
Tested up to: 6.3
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export WooCommerce products to Varle.lt XML format for automatic product import.

== Description ==

This plugin allows you to export your WooCommerce products to the XML format required by Varle.lt marketplace. The plugin generates an XML file that can be automatically imported by Varle.lt system.

**Features:**

- Automatic XML generation based on Varle.lt specifications
- Support for simple and variable products
- Configurable product attributes and settings
- Automatic generation when products are updated
- Manual generation and download options
- Product-specific Varle.lt settings
- Cron-based automatic updates

**Requirements:**

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/varle-export` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce > Varle.lt Export to configure the plugin
4. Generate your first XML file and provide the URL to Varle.lt

== Configuration ==

1. **General Settings:** Configure default delivery text, group ID, and manufacturer attribute
2. **XML File Name:** Set the name of your XML file (default: products.xml)
3. **Auto Generation:** Enable automatic XML generation when products are updated
4. **Product Settings:** Configure Varle-specific settings for individual products

== Usage ==

1. Configure the plugin settings
2. Click "Generate XML File" to create the export
3. Copy the XML file URL from the admin page
4. Provide this URL to Varle.lt in their import system
5. Varle.lt will automatically fetch updates from your URL

== Frequently Asked Questions ==

= How often is the XML file updated? =

The XML file is automatically updated when:

- Products are added, updated, or deleted
- Stock status changes
- Daily cron job runs (if auto-generation is enabled)

= Can I exclude specific products from export? =

Yes, you can exclude individual products by checking the "Exclude from Varle Export" option in the product edit page.

= What product information is exported? =

The plugin exports all required fields for Varle.lt including:

- Product ID, title, description, price
- Categories, images, stock quantity
- Product attributes, variants (for variable products)
- Custom Varle.lt settings (group, warranty, etc.)

= How do I troubleshoot XML generation issues? =

1. Check the plugin status in WooCommerce > Varle.lt Export
2. Verify that all products have required information (title, price, images)
3. Check WordPress error logs for detailed error messages
4. Ensure proper file permissions for XML file generation

== Screenshots ==

1. Main export interface with generation buttons and settings
2. Plugin settings page with configuration options
3. Product-specific Varle.lt settings in product edit page
4. XML file output example

== Changelog ==

= 1.0.0 =

- Initial release
- Basic XML export functionality
- Admin interface
- Product-specific settings
- Automatic generation features

== Upgrade Notice ==

= 1.0.0 =
Initial release of Varle.lt Export plugin.

== Technical Details ==

**XML Structure:**
The plugin generates XML according to Varle.lt specifications including all required and optional fields.

**Cron Jobs:**

- Daily automatic generation (if enabled)
- Triggered generation after product updates
- Manual cron execution support

**File Location:**
The XML file is generated in your website root directory and accessible via direct URL.

**Performance:**
The plugin is optimized for large product catalogs and includes memory management for bulk exports.

== Support ==

For support and bug reports, please contact the plugin developer or check the plugin documentation.

== License ==

This plugin is licensed under the GPL v2 or later.

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
