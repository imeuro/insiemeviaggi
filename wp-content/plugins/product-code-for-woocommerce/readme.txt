=== Product Code for WooCommerce ===

Contributors: Artiosmedia, steveneray, arafatrahmanbd
Donate link: https://www.zeffy.com/en-US/donation-form/your-donation-makes-a-difference-6
Tags: product code, product number, bin number, warehouse tracking, order number
Requires at least: 5.8
Tested up to: 6.9
Version: 1.5.11
Stable tag: 1.5.11
Requires PHP: 7.4.33
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This plugin will allow a user to add up to two additional internal product identifiers to the order process in addition to the GTIN, EAN, SKU, or UPC.

== Description ==

This user-friendly plugin is what many website designers, developers, and business owners look for when they need an additional product code field in WooCommerce. It is often used as an inventory control number, an internal stock number, or a bin location. The plugin allows you to add a product identifier to each variable or single item in WooCommerce. The custom field value can be passed through during order fulfillment and referenced from each item ordered. The field value can be viewed on the user side or turned off if desired.

Throughout the order process, a unique product code is often added in addition to the GTIN, EAN, SKU, and UPC. However, all current plugins that might address this need require complex setups and functions, resulting in increased memory usage, system conflicts, and frequent updates. This plugin eliminates all those hurdles by providing a simple solution without the bloat, without sacrificing the options WooCommerce doesn't offer.

WooCommerce's built-in product fields lack the flexibility many businesses require. This plugin fills those gaps with granular display controls—show codes to admins only, hide them on product pages while keeping them in cart and checkout, or hide them from customers entirely while preserving visibility on invoices and packing slips. A secondary code field accommodates businesses that need both a customer-facing code and an internal reference, such as a bin location. Customizable labels let you rename fields to match your workflow, and the option to hide empty fields keeps your product pages clean.

Simply install, enter your product codes within each product post (variation or single), and publish. There is nothing more to it than that! If you don't want customers to see the unique product code, you can disable the user-side display in setup. The field label can also be easily changed in setup to read ISBN, Bin Number, Stock Number, EAN, or JAN. Any value can be created and entered as a single new field.

The added fields are compliant with mappable data import and export schemes. This exact compliance allows the fields to be included in a Google Merchant product feed using custom mapping. It also supports Schema.org/Product with an option to choose the property name (GTIN, EAN, UPC, ISBN) to set inside the structured data.

You can also search product codes using the WordPress default search from the user side and from the administrator WooCommerce product list page on the backend. It is compatible to search product codes using the popular <a href="https://wordpress.org/plugins/relevanssi/" target="_blank">Relevanssi</a>, <a href="https://searchwp.com/" target="_blank">SearchWP</a> and, <a href="https://ajaxsearchpro.com/" target="_blank">Ajax Search Pro</a>.

= Translations =

All text strings use WordPress translation functions. Includes complete translations for English, Spanish, French, German, Portuguese, Dutch, Polish, Finnish, and Russian. Any edits to the PO files or additional languages are welcome.

= Donations =

If this free effort assists you, please consider making a small donation from the main plugin page, found on the lower right. All funds assist orphans in destitution.

= Version Changes =

As of <strong>version 1.3.1</strong>, the Product Code primary field is now displayed by default in the WooCommerce product panel, and can be toggled off from the top tab dropdown if desired. If your second Product Code field is activated from settings, it will appear to the right of the Product panel. Additionally, the Product Code primary field now appears in the WooCommerce Quick Edit panel. Turn on the second Product Code, which will display after the primary field in the Quick Edit panel. Make sure you clear your caches (website and browser) if you are updating from the previous plugin.

As of <strong>version 1.4.1</strong>, an administrator can choose to hide the default and secondary product code from the user-side product posts while the other display injections still work (checkout, cart, and receipts).

== Installation ==

1. Upload the plugin files to the '/wp-content/plugins/plugin-name' directory or install them directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Enter the Product Code from either Variable or Simple products under the SKU.

== Technical Details for Release 1.5.11 ==

Load time: 0.294 s; Memory usage: 3.63 MiB
PHP up to tested version: 8.3.29
MySQL up to tested version: 8.4.7
MariaDB up to tested version: 12.1
cURL up to tested version: 8.18.0, OpenSSL/3.6.0
PHP 7.4, 8.0, 8.1, 8.2, and 8.3 compliant. Not tested on 8.4 yet.

== Using in Multisite Installation ==

1. Extract the zip file contents into the wp-content/mu-plugins/ directory of your WordPress installation. (This is not created by default. You must create it in the wp-content folder.) The 'mu' does not stand for multi-user as it did for WPMU; it stands for 'must-use,' as any code placed in that folder will run without needing to be activated.
2. Access the Plugins settings panel named 'Product Code for WooCommerce' under options.
3. Enter the Product Code from either Variable or Simple products under the SKU.

== Configuration with Relevanssi plugin ==

1. Open up Indexing tab from Settings->Relevanssi page.
2. From the Post Type select "Product" and "Product Variation".
3. From the Custom fields dropdown select "Some" and add custom fields "_product_code" and "_product_code_second" and save.
4. Move to Searching tab and unselect checkbox "Respect exclude_from_search" and save.
5. Access Indexing tab, click button "Build the Index" and save.

== Configuration with SearchWP plugin ==

1. SearchWP requires the SearchWP WooCommerce Integration addon.
2. Open up Settings Tab from Settings->SearchWP page.
3. Add post type "Product" if not added by clicking "Add Post Type".
4. Click "Add Attributes", select "Custom Fields" and add "_product_code" and "_product_code_second" fields from the dropdown box. Move slider to right on both toward "Max".
5. Lastly click "Save Engines" and then click "Rebuild Index".

== Configuration with Ajax Search Pro plugin ==

1. Open up "Ajax Search Pro" settings page via admin menu.
2. Create/Edit the search instance.
3. Add "Products[product]" and "Variation[product_variation]" from the post types list.
4. Add "_product_code" and "_product_code_second" fields from the custom fields list and save.
5. If you have selected "Index table engine" for the search engine then index it again.

== Frequently Asked Questions ==

= Is this plugin frequently updated to Wordpress compliance? =
Yes, attention is given on a staged installation with many other plugins via debug mode.

= Is the plugin as simple to use as it looks? =
Yes. No other plugin exists that adds an additional custom product code so simply.

= Has there ever any compatibility issues? =
Point of Sale for WooCommerce 0.426 has been known to result in conflicts, but it cannot be replicated on a clean WooCommerce install with conflicting plugins present.

= Can the custom Product Code field be fed to Google Merchant? =
We can't possibly assure compatibility with every feed manager, but the properly built ones find the field correctly. We suggest using YITH Google Product Feed for WooCommerce Premium. The custom field appears as 'Product Id [id]' right on top of the custom field selections.

= How do I export the Product Code field from WooCommerce? =
We use WP All Export Pro by Soflyy which works great, but so does the free Advanced Order Export For WooCommerce By AlgolPlus.

- Click "Export Orders" under WooCommerce.
- Click to open "Set up fields to export"
- On the right click "Products"
- The field "[P] Product Id" is listed as the field to export from this plugin.

= Can I rename the Product Code field to another title? =
Previously, the function.php required a snippet addition to do so. As of version 1.0.6, in the settings panel, you will find an option to edit the field title with a limit of 18 characters, including spaces. Whatever title is entered will change on the user side and admin side, and throughout the order process.

= Is there short code that allows inserting product code? =
The shortcode to show the Product code is `[pcfw_display_product_code]` you can use these attributes:

• `id` the product id
• `pc_label` the product code label that will be displayed before the code. By default, it's "Product Code:", but this can be changed in the settings panel.
• `pcs_label` the product code second label that will be displayed before the code. By default is "Product Code Second:", but this can be changed inside the settings panel.
• `wrapper` you can wrap the label and product code in div or span. By default is 'div' for the shop page and 'span' on the other pages.
• `wrapper_code` the container of product code. By default is a 'span'.
• `class` the class of wrapper container. By default is 'pcfw_code'.
• `class_wrapper` the class of wrapper code container. By default is 'pcfw_code_wrapper'.

Note: For a variable product, you need to pass the variation product ID.

= Is the code in the plugin proven stable? =

Please click the following link to check the current stability of this plugin:
<a href="https://plugintests.com/plugins/product-code-for-woocommerce/latest" rel="nofollow ugc">https://plugintests.com/plugins/product-code-for-woocommerce/latest</a>

== Screenshots ==

1. The Product Code as found in a Simple Product
2. The Product Code as found in a Variable Product
3. The Product Code appears under the SKU on the user side
4. The Product Code appears below the description in the shopping cart
5. The Product Code appears below the SKU and Variation ID on the order page
6. The plugin's limited selection function settings panel
7. Product panel display and options along with Quick Edits presence

== Upgrade Notice ==

There is none to report as of the release version.

== Changelog ==

1.5.11 01/13/2026
- Fixed: Secondary product code now displays on variable product pages when "Show Second Code" is enabled

1.5.10 01/12/2026
- Fixed: "Hide from Customer Orders" correctly removes codes from the order confirmation page

1.5.9 01/11/2026
- Added: "Hide from Customer Orders" option - hides codes from cart, checkout, and order confirmations while preserving them for admin, invoices, and packing slips
- Added: Tooltips to all settings fields for improved clarity
- Improved: Settings page labels and descriptions rewritten for clarity
- Updated: Translation files for all 8 languages

1.5.8 01/10/2026
- NEW: Standalone settings page under WooCommerce menu
- NEW: "Delete Data on Uninstall" option with confirmation prompt
- NEW: Added translations for Dutch, Polish, Portuguese, and Finnish
- IMPROVED: Settings now preserved when the plugin is deactivated
- IMPROVED: Mobile-responsive settings page layout
- IMPROVED: Updated Spanish, French, German, and Russian translations
- FIXED: Checkbox settings not saving correctly in some cases
- Assure compliance with WooCommerce 10.4.3

1.5.7 12/15/2025
- Critical Hotfix: Removed undefined PRODUCT_CODE_COLOR constant causing fatal PHP 8.3
- Bug Fix: Removed deprecated inline color styling from product code display
- Updated several language files

1.5.6 12/15/2025
- Critical Fix: Restored missing 'wc-add-to-cart-variation' JavaScript dependency
- Bug Fix: Added array validation to cart methods to prevent type errors
- Bug Fix: Removed problematic do_shortcode() calls from cart that interfers with AJAX
- Improved compatibility with WPC Fly Cart and XT Ajax Add To Cart plugins

1.5.5 12/13/2025
- Bug Fix: Resolved fatal error breaking add-to-cart functionality
- Updated all language files

1.5.4 12/13/2025
- Added option to hide redundant WooCommerce Unique ID field
- Removed unused Product Code Color field
- Assure compliance with WooCommerce 10.4.2

1.5.3 12/13/2025
- Bug Fix: Array offset error on fresh installations

1.5.2 12/12/2025
- Bug Fix: The nag bar for Product Code for WooCommerce was not resetting
- Removed Composer dependency
- Assure compliance with WordPress 6.9.0
- Assure compliance with WooCommerce 10.4.0

1.5.1 05/08/2025
- Added nonce validation using check_ajax_referer to prevent Cross-Site Request Forgery (CSRF).
- Assure compliance with WordPress 6.8.1
- Assure compliance with WooCommerce 9.8.4

1.5.0 03/01/2025
- Added: Update allows plugin feedback functionality.
- Updated donation link to Zeffy 
- Assure compliance with WordPress 6.7.2
- Assure compliance with WooCommerce 9.7.0

1.4.9 01/11/25
- Added: Update language file for Product Code Color.
- Assure compliance with WooCommerce 9.5.2

1.4.8 12/27/24
- Fixed: Issue where the product code was not displaying on the Quick View modal
- Added: Support for hiding product codes using the shortcode 
- Added: Option to display product shelf location on backend while hidden on the frontend
- Added: Option to customize the color of the product code.
- Assure compliance with WordPress 6.7.1
- Assure compliance with WooCommerce 9.5.1

1.4.7 09/01/24
- Minor edits to language files
- Assure compliance with WordPress 6.6.1
- Assure compliance with WooCommerce 9.2.3

1.4.6 04/06/24
- Minor updates and edits
- Assure compliance with WordPress 6.5
- Assure compliance with WooCommerce 8.7.0

1.4.5 11/30/23
- Fixed Cross Site Scripting (XSS) vulnerability
- Add whitelist check for the wrapper tag

1.4.4 11/23/23
- Fixed index problem on the backend
- Assure compliance with WordPress 6.4.1
- Assure compliance with WooCommerce 8.3.1

1.4.3 10/17/23
- Make the product code column sortable
– Add German translation
- Assure compliance with WooCommerce 8.2.1

1.4.2 10/13/23
- Adjust the code field for diverse and longer entries
- Assure compliance with WordPress 6.3.2
- Assure compliance with WooCommerce 8.2.0

1.4.1 09/14/23
- Added an option for hiding product code on the user side
- Numerous settings composition changes
- Numerous text edits and tooltip changes
- Update all language files
- Assure compliance with WordPress 6.3.1
- Assure compliance with WooCommerce 8.1.0

1.4.0 08/11/23
- Added compatibility with WooCommerce HPOS
- Assure compliance with WordPress 6.3.0
- Assure compliance with WooCommerce 8.0.0

1.3.6 07/05/23
- Fixed single product page code conflict

1.3.5 07/04/23
- Fixed import problem of Product Code 2
- Fixed print on save post
- Assure compliance with WordPress 6.2.2
- Assure compliance with WooCommerce 7.8.0

1.3.4 02/08/23
- Update language files and add Russian translation
- Assure compliance with WordPress 6.1.1
- Assure compliance with WooCommerce 7.3.0

1.3.3 05/23/22
- Text edits along with translations
- Assure compliance with WordPress 6.0
- Assure compliance with WooCommerce 6.5.1

1.3.2 02/23/22
- Fixed extra space for variable product data in quick edit
- Enable editing of product column titles in the settings panel.
- Assure compliance with WordPress 5.9.1
- Assure compliance with WooCommerce 6.2.1

1.3.1 02/11/22
- Added product code field to WooCommerce Quick Edit
- Added product code 2 column in admin products panel if enabled
- Added allowing the custom title to appear on Quick Edit
- Assure compliance with WooCommerce 6.2.0

1.3.0 01/22/22
- Fixed redundant Ajax call on document load
- Added product code column on admin products page
- Updates for WordPress 5.9
- Assure current stable PHP 8.1.1 use
- Assure compliance with WooCommerce 6.1.1

1.2.8 08/28/21
- Fixed an offset issue

1.2.7 08/27/21
- Fixed save_post bug filling error log
- Assure MySQL stable 8.0.26 use
- Assure current stable PHP 8.0.10 use

1.2.6 08/24/21
- Fixed search results for variable products
- Fixed bullets, removed PHP and email conflict
- Updates for WordPress 5.8
- Assure compliance with WooCommerce 5.6.0

1.2.5 05/13/21
- Fixed CSS on the order received page.
- Fixed a typo in the enqueue script function
- Updates for WordPress 5.7.2
- Assure compliance with WooCommerce 5.3.0

1.2.4 12/31/20
- Fixed export of products with product code metafields
- Added setting to apply structure data property for product code
- Added 'N/A' for the structured data if the product code is not set for any product
- Added shortcode [pcfw_display_product_code] to display product code on a single product and custom pages
- Remove useless Import/Export Settings option

1.2.3 12/13/20
- Fixed variation search for search plugins
- Fixed search of product using product code at backend with search plugins
- Fixed not displaying of product code for variation product
- Fixed displaying of warning banner with new installs
- Fixed database upgrade script to be run after version 1.1.0
- Fixed displaying product code at cart, checkout, and receipt
- Added support for Schema.org/Product with an option to choose the property name to set inside the structured data

1.2.2 10/21/20
- Recompile to solve fatal errors

1.2.1 10/20/20
- Reload export module due to error

1.2.0 10/17/20
- Add second product code option
- Add script to merged two product meta values into single
- Add switch to hide user side field when the value is empty
- Update language files to include new fields
- Update sample screens to show new fields
- Assure compliance with WooCommerce 4.6.0
- Add Import/Export Settings option 

1.1.0 05/15/20
- Fixed search error on administrator product list page
- Updates for WordPress 5.4.1
- Assure compliance with WooCommerce 4.1.0

1.0.9 04/26/20
- Add search module for Relevanssi search

1.0.8 04/02/20
- Add search compliance, including third-party plugins
- Add a search module for SearchWP search
- Add search module for Ajax Search Pro search
- Updates for WordPress 5.4
- Assure compliance with WooCommerce 4.0.1

1.0.7 02/02/20
- Updates for WordPress 5.3.2
- Assure compliance with WooCommerce 3.9.2
- Fix missing Product Code label upon installation

1.0.6 12/11/19
- Updates for WordPress 5.3
- Assure compliance with WooCommerce 3.8.1
- Remove composer.json dependencies
- Add submenu access to setup
- Add the ability to edit the field title
- Overall composition and text edits
- Fix language POTS not loading

1.0.5 11/09/19
- Updates for WordPress 5.2.4
- Modifications for WooCommerce 3.8.0
- Support for WooCommerce Admin 0.21.0
- Tested Compatible with WPML
- Adjust for WooCommerce API REST
- Current version support updated

1.0.4 08/15/19
- Updates for WordPress 5.2.2
- Modifications for WooCommerce 3.7.0
- Current version support updated

1.0.3 05/05/19
- Updates for WordPress 5.2
- Assure compliance for WooCommerce 3.6.2
- Current version support updated

1.0.2 04/03/19
- Update to allow WordPress search of Product Code fields
- Assure compliance for WooCommerce 3.5.7
- Current version support updated

1.0.1 02/01/19
- Fix bug that caused code duplication in some variations
- Current version support updated

1.0.0 01/15/19
- Initial release