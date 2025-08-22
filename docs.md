# Complete WordPress Plugin Development Guide: Varle.lt Export Plugin

This guide explains how we built a complete WordPress plugin from scratch to export WooCommerce products to Varle.lt XML format.

## Table of Contents

1. [Understanding the Problem](#understanding-the-problem)
2. [Plugin Architecture](#plugin-architecture)
3. [File Structure](#file-structure)
4. [Step-by-Step Development](#step-by-step-development)
5. [How Each Component Works](#how-each-component-works)
6. [Testing and Debugging](#testing-and-debugging)
7. [Best Practices Learned](#best-practices-learned)
8. [Installation Instructions](#installation-instructions)

## Understanding the Problem

**What we needed to solve:**

- Export WooCommerce products to a specific XML format required by Varle.lt marketplace
- Generate XML file automatically when products change
- Provide a URL for Varle.lt to fetch product updates
- Handle both simple and variable products
- Include all required product information (categories, images, prices, variants, etc.)

**Why a custom plugin:**

- Existing export plugins don't match Varle.lt's specific XML structure
- Need automatic generation and hosting of XML file
- Require custom product fields for Varle-specific settings

## Plugin Architecture

Our plugin follows WordPress plugin best practices with a modular structure:

```
Main Plugin File (Entry Point)
├── XML Generator (Core Logic)
├── Admin Interface (User Interface)
├── Cron Handler (Automation)
├── CSS Styles (Admin Styling)
└── JavaScript (Admin Interactions)
```

**Key Components:**

| Component        | Purpose                                           | File                                 |
| ---------------- | ------------------------------------------------- | ------------------------------------ |
| Main Plugin File | Entry point, handles activation/deactivation      | `varle-export.php`                   |
| XML Generator    | Core logic for creating XML from WooCommerce data | `class-xml-generator.php`            |
| Admin Interface  | WordPress admin pages and settings                | `class-admin.php`                    |
| Cron Handler     | Automatic XML generation scheduling               | `class-cron.php`                     |
| Frontend Assets  | CSS and JavaScript for admin interface            | `admin-style.css`, `admin-script.js` |

## File Structure

```
varle-export/                          # Main plugin directory
├── varle-export.php                   # Main plugin file (entry point)
├── readme.txt                         # Plugin documentation
├── includes/                          # Core functionality
│   ├── class-xml-generator.php        # XML generation logic
│   ├── class-admin.php                # Admin interface
│   └── class-cron.php                 # Scheduled tasks
├── admin/                             # Admin-specific assets
│   ├── css/
│   │   └── admin-style.css            # Admin styling
│   └── js/
│       └── admin-script.js            # Admin JavaScript
└── assets/                            # Plugin assets (optional)
    └── icon-128x128.png               # Plugin icon
```

## Step-by-Step Development

### Step 1: Create Main Plugin File

**File:** `varle-export.php`

**Purpose:** This is the entry point that WordPress recognizes as a plugin.

**Key Elements:**

```php
<?php
/**
 * Plugin Name: Varle.lt Product Export
 * Description: Export WooCommerce products to Varle.lt XML format
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
```

**What this does:**

- **Plugin Header:** WordPress reads this to identify the plugin
- **Security Check:** Prevents direct file access
- **Constants:** Define plugin paths and URLs for other files to use
- **Initialization:** Loads other components when WordPress starts

**WordPress Hooks Used:**

| Hook                         | Purpose                      | When It Fires               |
| ---------------------------- | ---------------------------- | --------------------------- |
| `init`                       | Initialize plugin components | When WordPress initializes  |
| `admin_menu`                 | Add admin menu items         | When admin menus are built  |
| `wp_ajax_*`                  | Handle AJAX requests         | When AJAX requests are made |
| `register_activation_hook`   | Plugin activation tasks      | When plugin is activated    |
| `register_deactivation_hook` | Plugin deactivation tasks    | When plugin is deactivated  |

### Step 2: Create XML Generator Class

**File:** `includes/class-xml-generator.php`

**Purpose:** Contains all logic for converting WooCommerce data to Varle.lt XML format.

**Key Methods:**

| Method                   | Purpose                             | Visibility |
| ------------------------ | ----------------------------------- | ---------- |
| `generate_xml_file()`    | Main method - creates and saves XML | public     |
| `generate_xml_content()` | Creates XML content string          | public     |
| `add_products_to_xml()`  | Loops through WooCommerce products  | private    |
| `add_single_product()`   | Converts one product to XML         | private    |
| `add_categories()`       | Handles product categories          | private    |
| `add_images()`           | Handles product images              | private    |
| `add_variants()`         | Handles variable products           | private    |

**How it works:**

1. **Query Products:** Uses `WP_Query` to get all published, in-stock products
2. **Create XML Structure:** Uses PHP's `DOMDocument` to build valid XML
3. **Map Data:** Converts WooCommerce fields to Varle.lt XML structure
4. **Handle Variants:** Special logic for variable products (size, color options)
5. **Save File:** Writes XML to server for Varle.lt to access

**Data Mapping Example:**

| WooCommerce Field          | Varle.lt XML                                        | Method Used           |
| -------------------------- | --------------------------------------------------- | --------------------- |
| `$product->get_id()`       | `<id>123</id>`                                      | `add_element()`       |
| `$product->get_name()`     | `<title><![CDATA[Product Name]]></title>`           | `add_cdata_element()` |
| `$product->get_price()`    | `<price>19.99</price>`                              | `add_element()`       |
| `get_the_terms()`          | `<categories><category>...</category></categories>` | `add_categories()`    |
| `$product->get_image_id()` | `<images><image>...</image></images>`               | `add_images()`        |

### Step 3: Create Admin Interface

**File:** `includes/class-admin.php`

**Purpose:** Provides WordPress admin interface for plugin settings and controls.

**Key Features:**

- Settings page under WooCommerce menu
- Form to configure plugin options
- Buttons to generate/download XML
- Display of XML file URL for Varle.lt
- Product-specific settings in product edit pages

**WordPress Admin Integration:**

```php
add_submenu_page(
    'woocommerce',              // Parent menu
    'Varle.lt Export',          // Page title
    'Varle.lt Export',          // Menu title
    'manage_woocommerce',       // Required capability
    'varle-export',             // Menu slug
    array($this, 'admin_page')  // Callback function
);
```

**Settings API Integration:**

| Function                 | Purpose                        |
| ------------------------ | ------------------------------ |
| `register_setting()`     | Register setting group         |
| `add_settings_section()` | Add settings section           |
| `add_settings_field()`   | Add individual setting field   |
| `settings_fields()`      | Output nonce and hidden fields |
| `do_settings_sections()` | Display all fields in section  |

### Step 4: Create Cron Handler

**File:** `includes/class-cron.php`

**Purpose:** Handles automatic XML generation when products change.

**Scheduling Logic:**

```php
// When product is updated
add_action('woocommerce_update_product', 'schedule_xml_generation');

// Daily automatic generation
wp_schedule_event(time(), 'daily', 'varle_export_daily');
```

**How it works:**

1. **Hook into Product Events:** Listens for product saves/updates
2. **Schedule Generation:** Delays generation by 2 minutes to batch updates
3. **Cron Execution:** WordPress cron system runs the generation
4. **Error Handling:** Logs errors and optionally sends email notifications

**WordPress Cron Events:**

| Event                  | Schedule | Purpose                                  |
| ---------------------- | -------- | ---------------------------------------- |
| `varle_export_daily`   | daily    | Regular XML generation                   |
| `varle_export_delayed` | single   | Delayed generation after product updates |
| `varle_export_hourly`  | hourly   | Frequent updates (optional)              |

### Step 5: Create Admin Assets

**File:** `admin/css/admin-style.css`
**Purpose:** Styles the admin interface to look professional and integrated.

**File:** `admin/js/admin-script.js`
**Purpose:** Handles AJAX interactions for generating XML without page reload.

**AJAX Flow:**

```javascript
// User clicks "Generate XML"
$('#generate-xml').click()
    ↓
// Send AJAX request to WordPress
$.ajax({
    url: ajaxurl,
    action: 'varle_generate_xml'
})
    ↓
// PHP handler processes request
handle_ajax_generate()
    ↓
// Return success/error to JavaScript
wp_send_json_success()
    ↓
// Update UI with result
showSuccess() or showError()
```

## How Each Component Works

### XML Generation Deep Dive

**1. Product Query:**

```php
$args = array(
    'post_type' => 'product',           // Only products
    'post_status' => 'publish',         // Only published
    'posts_per_page' => -1,             // Get all products
    'meta_query' => array(              // Additional filters
        array(
            'key' => '_stock_status',
            'value' => 'instock'
        )
    )
);
$products = new WP_Query($args);
```

**2. XML Structure Creation:**

```php
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;              // Pretty formatting

$root = $xml->createElement('root');
$xml->appendChild($root);

$products_element = $xml->createElement('products');
$root->appendChild($products_element);
```

**3. Product Data Mapping:**

```php
// Required fields
$this->add_element($xml, $product_element, 'id', $product->get_id());
$this->add_cdata_element($xml, $product_element, 'title', $product->get_name());
$this->add_element($xml, $product_element, 'price', $product->get_price());

// Categories with hierarchy
$categories = get_the_terms($product_id, 'product_cat');
foreach ($categories as $category) {
    $path = $this->get_category_path($category); // "Parent -> Child"
    $this->add_cdata_element($xml, $categories_element, 'category', $path);
}
```

**4. Variable Products Handling:**

```php
if ($product->is_type('variable')) {
    $variations = $product->get_available_variations();
    foreach ($variations as $variation_data) {
        $variation = wc_get_product($variation_data['variation_id']);
        // Create variant XML elements
        // Map size/color attributes to Varle format
    }
}
```

### Admin Interface Deep Dive

**1. Menu Integration:**
WordPress provides hooks to add custom admin pages:

```php
add_action('admin_menu', array($this, 'add_admin_menu'));
```

**2. Settings Form:**
Uses WordPress Settings API for consistent UI:

```php
settings_fields('varle_export_settings');       // Nonce and hidden fields
do_settings_sections('varle_export_settings');  // Display all fields
submit_button();                                 // Standard submit button
```

**3. AJAX Handlers:**
WordPress AJAX system handles async requests:

| AJAX Action                  | Purpose                 | Security              |
| ---------------------------- | ----------------------- | --------------------- |
| `wp_ajax_varle_export_xml`   | Download XML file       | Nonce verification    |
| `wp_ajax_varle_generate_xml` | Generate XML file       | Capability check      |
| `wp_ajax_varle_check_status` | Check generation status | Permission validation |

### File Management Deep Dive

**1. WordPress Uploads Directory:**

```php
$upload_dir = wp_upload_dir();
// Returns:
// ['basedir'] = '/path/to/wp-content/uploads'
// ['baseurl'] = 'http://site.com/wp-content/uploads'
```

**2. Directory Creation:**

```php
wp_mkdir_p($directory);  // WordPress function, creates parent dirs
// Equivalent to: mkdir($directory, 0755, true);
```

**3. File Writing with Error Handling:**

```php
$result = file_put_contents($file_path, $xml_content);
if ($result === false) {
    throw new Exception('Failed to write file: ' . $file_path);
}
```

**File Location Strategy:**

| Location          | Purpose          | Pros            | Cons                    |
| ----------------- | ---------------- | --------------- | ----------------------- |
| Website root      | Clean URLs       | Direct access   | Permission issues       |
| Uploads directory | Reliable writing | Always writable | Longer URLs             |
| Plugin directory  | Self-contained   | Easy management | Not publicly accessible |

## Testing and Debugging

### Debug Process We Used

**1. Enable WordPress Debug Mode:**

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**2. Check Error Logs:**

| Log Type  | Location                   | Purpose                     |
| --------- | -------------------------- | --------------------------- |
| WordPress | `/wp-content/debug.log`    | WordPress and plugin errors |
| Server    | Apache/PHP error logs      | Server-level errors         |
| Plugin    | Custom `error_log()` calls | Specific plugin debugging   |

**3. Permission Issues:**
Most common problem - fixed with:

```bash
chmod 755 /path/to/directory/
chown user:group /path/to/directory/
```

**4. Step-by-Step Testing:**

- Created minimal test plugin first
- Added functionality incrementally
- Used debug output at each step
- Tested file creation separately

### Common Issues and Solutions

| Issue                         | Cause                                               | Solution                                                   | Prevention                                |
| ----------------------------- | --------------------------------------------------- | ---------------------------------------------------------- | ----------------------------------------- |
| Plugin Won't Activate         | PHP syntax errors, missing files                    | Check error logs, validate PHP syntax                      | Use `php -l filename.php` to check syntax |
| File Permission Denied        | Web server can't write to directory                 | Fix directory permissions, use WordPress uploads directory | Always use WordPress file functions       |
| XML Generation Fails Silently | PHP errors, memory limits, missing data             | Add extensive error logging and exception handling         | Validate all data before processing       |
| AJAX Requests Fail            | Missing nonce, wrong action name, permission issues | Check WordPress AJAX documentation, verify nonces          | Use WordPress AJAX patterns exactly       |

## Best Practices Learned

### 1. WordPress Coding Standards

**File Organization:**

- One class per file
- Descriptive file names
- Logical directory structure
- Follow WordPress naming conventions

**Security:**

```php
// Always prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Validate user permissions
if (!current_user_can('manage_woocommerce')) {
    wp_die('Insufficient permissions');
}

// Verify nonces for form submissions
wp_verify_nonce($_POST['nonce'], 'action_name');

// Sanitize all input
$clean_data = sanitize_text_field($_POST['data']);

// Escape all output
echo esc_html($user_data);
echo esc_url($url);
```

### 2. Error Handling

**Comprehensive Logging:**

```php
try {
    // Risky operation
    $result = some_operation();
    error_log('Success: Operation completed');
} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage());
    return false;
}
```

**Graceful Degradation:**

```php
// Try preferred method, fall back to alternatives
if (method_exists($object, 'preferred_method')) {
    return $object->preferred_method();
} else {
    return $object->fallback_method();
}
```

### 3. WordPress Integration

**Use WordPress Functions:**

| Task              | WordPress Way ✓                  | Direct PHP Way ✗            |
| ----------------- | -------------------------------- | --------------------------- |
| Create directory  | `wp_mkdir_p($directory)`         | `mkdir($directory)`         |
| Get upload path   | `wp_upload_dir()`                | `$_SERVER['DOCUMENT_ROOT']` |
| Check permissions | `current_user_can('capability')` | `$_SESSION['user_role']`    |
| Schedule tasks    | `wp_schedule_event()`            | `crontab -e`                |
| Database queries  | `$wpdb->get_results()`           | `mysql_query()`             |

**Hook into WordPress Events:**

```php
// Listen for relevant events
add_action('woocommerce_update_product', 'callback');
add_action('admin_menu', 'callback');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'callback');
```

### 4. Performance Considerations

**Memory Management:**

```php
// For large datasets
ini_set('memory_limit', '512M');
set_time_limit(300);

// Process in batches
$offset = 0;
$batch_size = 100;
while ($products = get_products($offset, $batch_size)) {
    process_batch($products);
    $offset += $batch_size;
}
```

**Caching:**

```php
// Cache expensive operations
$cached_data = get_transient('cache_key');
if ($cached_data === false) {
    $cached_data = expensive_operation();
    set_transient('cache_key', $cached_data, HOUR_IN_SECONDS);
}
```

### 5. User Experience

**Provide Feedback:**

- Loading states for long operations
- Clear error messages
- Success confirmations
- Progress indicators

**Documentation:**

- Clear instructions in admin interface
- Helpful descriptions for settings
- Links to documentation
- Contact information for support

## Installation Instructions

### Development Environment Setup

**1. Create Plugin Directory:**

```bash
mkdir /wp-content/plugins/varle-export/
```

**2. Create File Structure:**

```
varle-export/
├── varle-export.php
├── includes/
│   ├── class-xml-generator.php
│   ├── class-admin.php
│   └── class-cron.php
├── admin/
│   ├── css/admin-style.css
│   └── js/admin-script.js
└── readme.txt
```

**3. Set Permissions:**

```bash
chmod -R 755 /wp-content/plugins/varle-export/
chmod 777 /wp-content/uploads/
```

**4. Activate Plugin:**

- Go to WordPress Admin → Plugins
- Find "Varle.lt Product Export"
- Click "Activate"

**5. Configure Settings:**

- Go to WooCommerce → Varle.lt Export
- Set delivery text, group ID, manufacturer attribute
- Generate first XML file
- Copy URL for Varle.lt import system

### Production Deployment

| Step | Task                          | Notes                              |
| ---- | ----------------------------- | ---------------------------------- |
| 1    | Upload plugin files           | Via FTP or hosting file manager    |
| 2    | Check file permissions        | Uploads directory must be writable |
| 3    | Test XML generation           | With sample products               |
| 4    | Verify XML file accessibility | Via direct URL                     |
| 5    | Provide URL to Varle.lt       | For automatic imports              |
| 6    | Monitor error logs            | For any issues                     |
| 7    | Set up automated backups      | Of plugin settings                 |

### Troubleshooting Common Issues

| Problem               | Symptoms                         | Solution                                                                                                          |
| --------------------- | -------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Plugin won't activate | Error messages, white screen     | Check PHP error logs, verify file permissions, ensure WooCommerce is active                                       |
| XML generation fails  | "Failed to generate" error       | Check directory permissions, verify products exist and are in stock, review WordPress debug logs                  |
| File not accessible   | 404 error when accessing XML URL | Confirm uploads directory is publicly accessible, check .htaccess restrictions, verify file was actually created  |
| Performance issues    | Timeouts, memory errors          | Increase PHP memory limit, set higher execution time limit, consider generating XML via WP-CLI for large catalogs |

## Key Takeaways

1. **Start Simple:** Begin with basic functionality, add features incrementally
2. **Use WordPress APIs:** Don't reinvent the wheel, use built-in functions
3. **Handle Errors Gracefully:** Assume things will go wrong, plan for it
4. **Test Thoroughly:** Test on different environments, with different data
5. **Follow Conventions:** Use WordPress coding standards and patterns
6. **Document Everything:** Clear comments, user documentation, installation guides
7. **Security First:** Validate input, escape output, check permissions
8. **Performance Matters:** Consider memory usage, execution time, caching

## Plugin Development Workflow

### Planning Phase

1. **Define Requirements:** What problem does the plugin solve?
2. **Research Existing Solutions:** Are there similar plugins?
3. **Plan Architecture:** How will components interact?
4. **Choose Technologies:** What WordPress APIs will you use?

### Development Phase

1. **Create Basic Structure:** Main plugin file and directory structure
2. **Implement Core Functionality:** The main logic your plugin needs
3. **Add Admin Interface:** User-facing controls and settings
4. **Implement Automation:** Cron jobs, hooks, scheduled tasks
5. **Style and Polish:** CSS, JavaScript, user experience improvements

### Testing Phase

1. **Local Testing:** Test on development environment
2. **Edge Case Testing:** What happens with no data, bad data, etc.?
3. **Performance Testing:** How does it handle large datasets?
4. **Security Testing:** Check for vulnerabilities, proper sanitization
5. **Cross-Environment Testing:** Different PHP versions, WordPress versions

### Deployment Phase

1. **Documentation:** README, installation guide, user manual
2. **Version Control:** Tag releases, maintain changelog
3. **Distribution:** WordPress.org repository or private distribution
4. **Support:** How will users get help?
5. **Maintenance:** Regular updates, security patches, feature additions

This plugin development process taught us how WordPress plugins work under the hood, how to integrate with WooCommerce, handle file operations, create admin interfaces, and debug complex issues. The modular approach made it maintainable and the WordPress integration made it professional and secure.
