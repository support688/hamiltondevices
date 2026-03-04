<?php
/**
 * Hamilton Devices — Production Migration Script
 * 
 * Migrates all database content from local dev to production.
 * Theme files and uploads must be deployed via Git first.
 * 
 * Usage: wp eval-file migrate-to-production.php
 * 
 * Requirements:
 *   1. Theme files copied to production (flatsome-child/)
 *   2. Upload files copied to production (uploads/2026/)
 *   3. WP-CLI available on production server
 * 
 * Every section is idempotent — safe to run multiple times.
 */

if (!defined("ABSPATH")) {
    echo "ERROR: This script must be run via WP-CLI: wp eval-file migrate-to-production.php\n";
    exit(1);
}

// Ensure WooCommerce is loaded
if (!function_exists("wc_get_product_id_by_sku")) {
    echo "ERROR: WooCommerce is not active.\n";
    exit(1);
}

$migration_log = array();
function mlog($msg) {
    global $migration_log;
    $migration_log[] = $msg;
    echo $msg . "\n";
}

/**
 * Create an attachment from an existing file in the uploads directory.
 * Returns the new attachment ID, or existing ID if already created.
 */
function hd_create_attachment($file_path, $title = "") {
    $upload_dir = wp_upload_dir();
    $full_path = $upload_dir["basedir"] . "/" . $file_path;
    
    if (!file_exists($full_path)) {
        mlog("  WARNING: File not found: $file_path");
        return 0;
    }
    
    // Check if attachment already exists for this file
    global $wpdb;
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        "_wp_attached_file", $file_path
    ));
    if ($existing) return (int)$existing;
    
    $filetype = wp_check_filetype(basename($full_path));
    if (empty($title)) {
        $title = preg_replace("/\.[^.]+$/", "", basename($full_path));
        $title = str_replace(array("-", "_"), " ", $title);
        $title = ucwords($title);
    }
    
    $attachment = array(
        "guid"           => $upload_dir["baseurl"] . "/" . $file_path,
        "post_mime_type" => $filetype["type"],
        "post_title"     => $title,
        "post_content"   => "",
        "post_status"    => "inherit",
    );
    
    $attach_id = wp_insert_attachment($attachment, $full_path);
    if (is_wp_error($attach_id)) {
        mlog("  ERROR creating attachment for $file_path: " . $attach_id->get_error_message());
        return 0;
    }
    
    require_once(ABSPATH . "wp-admin/includes/image.php");
    $metadata = wp_generate_attachment_metadata($attach_id, $full_path);
    wp_update_attachment_metadata($attach_id, $metadata);
    
    return $attach_id;
}

/**
 * Replace Docker image IDs in content with production IDs.
 */
function hd_replace_image_ids($content, $id_map) {
    if (empty($content) || empty($id_map)) return $content;
    
    // Replace [ux_image id="XXXXX"] patterns
    $content = preg_replace_callback('/\[ux_image([^\]]*?)id="(\d+)"([^\]]*?)\]/', function($matches) use ($id_map) {
        $old_id = $matches[2];
        if (isset($id_map[$old_id])) {
            return '[ux_image' . $matches[1] . 'id="' . $id_map[$old_id] . '"' . $matches[3] . ']';
        }
        return $matches[0];
    }, $content);
    
    // Replace [ux_banner ... bg="XXXXX"] patterns  
    $content = preg_replace_callback('/\[ux_banner([^\]]*?)bg="(\d+)"([^\]]*?)\]/', function($matches) use ($id_map) {
        $old_id = $matches[2];
        if (isset($id_map[$old_id])) {
            return '[ux_banner' . $matches[1] . 'bg="' . $id_map[$old_id] . '"' . $matches[3] . ']';
        }
        return $matches[0];
    }, $content);
    
    return $content;
}

mlog("=== Hamilton Devices Production Migration ===");
mlog("Started: " . date("Y-m-d H:i:s"));
mlog("");


// ============================================================
// SECTION 1: Technology Categories
// ============================================================
mlog("--- Section 1: Technology Categories ---");

$tech_categories = array(
    array(
        'slug' => 'ccell-easy',
        'name' => 'CCELL Easy Cart',
        'parent_slug' => 'cartridge',
        'description' => 'CCELL Easy Cart series with SE ceramic heating. Best value CCELL cartridges, snap-fit closure, optimized for high-volume distillate programs.',
    ),
    array(
        'slug' => 'ccell-se',
        'name' => 'CCELL SE Glass',
        'parent_slug' => 'cartridge',
        'description' => 'CCELL SE platform glass cartridges with screw-on mouthpiece. 1.4 ohm resistance, 510 thread, optimized for distillate.',
    ),
    array(
        'slug' => 'ccell-evo-max',
        'name' => 'CCELL EVO MAX',
        'parent_slug' => 'cartridge',
        'description' => 'Premium CCELL cartridges featuring the advanced EVO MAX heating coil for superior vapor quality and consistency. Available in glass (TH2) and polycarbonate (M6T) bodies.',
    ),
    array(
        'slug' => 'ccell-ceramic-evo-max',
        'name' => 'CCELL Ceramic EVO MAX',
        'parent_slug' => 'cartridge',
        'description' => 'Premium ceramic-body cartridges with EVO MAX heating technology. Eliminates metal contact with oil for the purest flavor profile.',
    ),
    array(
        'slug' => 'ccell-3-postless',
        'name' => 'CCELL 3.0 Postless',
        'parent_slug' => 'cartridge',
        'description' => 'Next-generation postless cartridges featuring CCELL 3.0 heating technology. The Klean series offers the cleanest oil path and easiest filling process.',
    ),
    array(
        'slug' => 'aio-se-standard',
        'name' => 'AIO SE (Standard)',
        'parent_slug' => 'disposable',
        'description' => 'Economy AIO disposables with proven SE ceramic heating. Best for distillate programs.',
    ),
    array(
        'slug' => 'aio-evo-max',
        'name' => 'AIO EVO MAX',
        'parent_slug' => 'disposable',
        'description' => 'Premium all-oil AIO disposables with EVO MAX heating. Works with every oil type.',
    ),
    array(
        'slug' => 'aio-3-bio-heating',
        'name' => 'AIO 3.0 Bio-Heating',
        'parent_slug' => 'disposable',
        'description' => 'Next-generation AIO disposables with CCELL 3.0 Bio-Heating and postless design.',
    ),
    array(
        'slug' => 'aio-hero',
        'name' => 'AIO HeRo',
        'parent_slug' => 'disposable',
        'description' => 'CCELL HeRo heating platform — partitioned atomization for rosin and live resin formulations.',
    ),
    array(
        'slug' => 'ccell-classics',
        'name' => 'CCELL Classics',
        'parent_slug' => '',
        'description' => 'Legacy and discontinued CCELL products. These products are no longer in active production but pages are preserved for reference.',
    ),
);

foreach ($tech_categories as $cat) {
    $exists = term_exists($cat["slug"], "product_cat");
    if ($exists) {
        mlog("  SKIP: {$cat['slug']} already exists");
        // Update description if needed
        $term = get_term_by("slug", $cat["slug"], "product_cat");
        if ($term && $term->description !== $cat["description"]) {
            wp_update_term($term->term_id, "product_cat", array("description" => $cat["description"]));
            mlog("  UPDATED description for {$cat['slug']}");
        }
        continue;
    }
    
    $parent_id = 0;
    if (!empty($cat["parent_slug"])) {
        $parent_term = get_term_by("slug", $cat["parent_slug"], "product_cat");
        if ($parent_term) $parent_id = $parent_term->term_id;
    }
    
    $result = wp_insert_term($cat["name"], "product_cat", array(
        "slug" => $cat["slug"],
        "parent" => $parent_id,
        "description" => $cat["description"],
    ));
    
    if (is_wp_error($result)) {
        mlog("  ERROR: {$cat['slug']}: " . $result->get_error_message());
    } else {
        mlog("  CREATED: {$cat['slug']}");
    }
}
mlog("");


// ============================================================
// SECTION 2: Pages
// ============================================================
mlog("--- Section 2: Pages ---");

$pages_to_create = array(
    array(
        'slug' => 'custom-branding',
        'title' => 'Custom Branding',
        'content' => '[section bg_color="rgb(26,26,26)" padding="80px" class="custom-branding-hero"]
[row]
[col span="7" span__sm="12"]
[ux_text font_size="1.1" text_color="rgb(255,255,255)"]
<p style="text-transform:uppercase;letter-spacing:3px;color:#E50914;font-weight:600;margin-bottom:10px;">Authorized CCELL® Distributor</p>
[/ux_text]
[ux_text font_size="2.4" text_color="rgb(255,255,255)"]
<h1 style="color:#fff;line-height:1.15;">Your Brand.<br>Our Hardware.<br>Factory Direct.</h1>
[/ux_text]
[gap height="10px"]
[ux_text text_color="rgb(200,200,200)"]
<p style="font-size:18px;line-height:1.7;">Hamilton Devices offers full custom branding on the complete CCELL® product line. From laser engraving to full-color screen printing, we help cannabis brands put their identity on the highest quality vaporizer hardware in the industry.</p>
[/ux_text]
[gap height="20px"]
[button text="Request Samples" color="white" style="outline" size="larger" link="/request-samples/"]
[button text="Contact Us" size="larger" link="/contact/"]
[/col]
[/row]
[/section]

[section padding="60px"]
[row]
[col span__sm="12"]
[ux_text font_size="1.8" text_align="center"]
<h2>Customization Capabilities</h2>
[/ux_text]
[ux_text text_align="center"]
<p style="font-size:17px;max-width:700px;margin:0 auto 40px;">Every CCELL® product we distribute can be customized with your brand identity. Here is what we offer.</p>
[/ux_text]
[/col]
[/row]

[row style="small" col_bg="rgb(248,248,248)" col_bg_radius="12"]
[col span="4" span__sm="12" padding="30px" align="center"]
[featured_box img_width="60" pos="center" icon_color="rgb(229,9,20)"]
<h3>Laser Engraving</h3>
<p>Precision laser engraving on metal and glass components. Creates a permanent, premium-feel mark on mouthpieces, housings, and cartridge bodies.</p>
[/featured_box]
[/col]
[col span="4" span__sm="12" padding="30px" align="center"]
[featured_box img_width="60" pos="center" icon_color="rgb(229,9,20)"]
<h3>Screen Printing</h3>
<p>Full-color screen printing on metal, glass, and plastic surfaces. Pantone color matching ensures your brand colors are represented exactly as intended.</p>
[/featured_box]
[/col]
[col span="4" span__sm="12" padding="30px" align="center"]
[featured_box img_width="60" pos="center" icon_color="rgb(229,9,20)"]
<h3>Custom Colors</h3>
<p>For plastic components, we can manufacture the base material in any color you choose, then apply screen printing over top for a fully custom look.</p>
[/featured_box]
[/col]
[/row]
[/section]

[section bg_color="rgb(248,248,248)" padding="60px"]
[row]
[col span__sm="12"]
[ux_text font_size="1.8" text_align="center"]
<h2>How It Works</h2>
[/ux_text]
[ux_text text_align="center"]
<p style="font-size:17px;max-width:700px;margin:0 auto 40px;">From concept to delivery, we manage the entire custom branding process with CCELL\'s factory.</p>
[/ux_text]
[/col]
[/row]

[row h_align="center"]
[col span="2" span__sm="6" padding="20px" align="center"]
[ux_text text_align="center"]
<div style="width:60px;height:60px;border-radius:50%;background:#E50914;color:#fff;font-size:24px;font-weight:bold;line-height:60px;margin:0 auto 15px;">1</div>
<h4>Choose Your Products</h4>
<p>Select from the full CCELL® lineup: cartridges, all-in-ones, disposables, pods, or batteries.</p>
[/ux_text]
[/col]
[col span="2" span__sm="6" padding="20px" align="center"]
[ux_text text_align="center"]
<div style="width:60px;height:60px;border-radius:50%;background:#E50914;color:#fff;font-size:24px;font-weight:bold;line-height:60px;margin:0 auto 15px;">2</div>
<h4>Submit Your Artwork</h4>
<p>We provide the die-line template for your selected product. Your art team places your logo, artwork, and Pantone colors.</p>
[/ux_text]
[/col]
[col span="2" span__sm="6" padding="20px" align="center"]
[ux_text text_align="center"]
<div style="width:60px;height:60px;border-radius:50%;background:#E50914;color:#fff;font-size:24px;font-weight:bold;line-height:60px;margin:0 auto 15px;">3</div>
<h4>Review Samples</h4>
<p>The factory produces physical samples of your custom branded product. We ship them to you for approval before production begins.</p>
[/ux_text]
[/col]
[col span="2" span__sm="6" padding="20px" align="center"]
[ux_text text_align="center"]
<div style="width:60px;height:60px;border-radius:50%;background:#E50914;color:#fff;font-size:24px;font-weight:bold;line-height:60px;margin:0 auto 15px;">4</div>
<h4>Approve &amp; Produce</h4>
<p>Once you approve the samples, your order goes into full production at the CCELL® factory.</p>
[/ux_text]
[/col]
[col span="2" span__sm="6" padding="20px" align="center"]
[ux_text text_align="center"]
<div style="width:60px;height:60px;border-radius:50%;background:#E50914;color:#fff;font-size:24px;font-weight:bold;line-height:60px;margin:0 auto 15px;">5</div>
<h4>Direct Shipping</h4>
<p>Finished products ship directly to you from the factory. We handle all logistics and customs.</p>
[/ux_text]
[/col]
[/row]
[/section]

[section padding="60px"]
[row]
[col span__sm="12"]
[ux_text font_size="1.8" text_align="center"]
<h2>Products Available for Custom Branding</h2>
[/ux_text]
[gap height="20px"]
[/col]
[/row]

[row style="small" col_bg="rgb(248,248,248)" col_bg_radius="12"]
[col span="3" span__sm="6" padding="25px" align="center"]
<h4>510 Cartridges</h4>
<p>The industry standard. Available in multiple sizes and configurations with full custom branding options.</p>
[button text="View Cartridges" style="outline" size="small" link="product-category/cartridge/"]
[/col]
[col span="3" span__sm="6" padding="25px" align="center"]
<h4>All-In-One Disposables</h4>
<p>Complete disposable vaporizer units. Custom colors, printing, and engraving available on all models.</p>
[button text="View All-In-Ones" style="outline" size="small" link="product-category/vaporizers/"]
[/col]
[col span="3" span__sm="6" padding="25px" align="center"]
<h4>Pod Systems</h4>
<p>Closed-loop pod systems for brands seeking a proprietary form factor with custom branding throughout.</p>
[button text="View Pod Systems" style="outline" size="small" link="product-category/pod-systems/"]
[/col]
[col span="3" span__sm="6" padding="25px" align="center"]
<h4>Batteries &amp; Power Supplies</h4>
<p>CCELL® batteries and power supplies with your logo for a complete branded experience.</p>
[button text="View Batteries" style="outline" size="small" link="product-category/batteries/"]
[/col]
[/row]
[/section]

[section bg_color="rgb(26,26,26)" padding="50px"]
[row]
[col span__sm="12" align="center"]
[ux_text font_size="1.6" text_align="center" text_color="rgb(255,255,255)"]
<h2 style="color:#fff;">Ready to Put Your Brand on CCELL® Hardware?</h2>
[/ux_text]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<p style="font-size:18px;color:rgba(255,255,255,0.9);max-width:600px;margin:0 auto 25px;">Request samples, get a custom branding quote, or talk to our team about your project.</p>
[/ux_text]
[button text="Request Samples" size="larger" link="/request-samples/"]
[button text="Contact Our Team" color="white" style="outline" size="larger" link="/contact/"]
[/col]
[/row]
[/section]',
        'template' => 'page-blank.php',
        'status' => 'publish',
    ),
    array(
        'slug' => 'request-samples',
        'title' => 'Request Samples',
        'content' => '[section bg_color="rgb(0,0,0)" padding="60px" class="samples-hero"]
[row]
[col span="7" span__sm="12"]
[ux_text font_size="1.1" text_color="rgb(255,255,255)"]
<p style="text-transform:uppercase;letter-spacing:3px;color:#c72035;font-weight:600;margin-bottom:10px;">Authorized CCELL® Distributor</p>
[/ux_text]
[ux_text font_size="2.2" text_color="rgb(255,255,255)"]
<h1 style="color:#fff;line-height:1.15;">Request Product Samples</h1>
[/ux_text]
[gap height="10px"]
[ux_text text_color="rgb(200,200,200)"]
<p style="font-size:18px;line-height:1.7;">Evaluating CCELL® hardware for your brand? Tell us about your business and the products you are interested in. Approved brands receive complimentary samples so you can test the quality firsthand before placing a bulk order.</p>
[/ux_text]
[/col]
[/row]
[/section]

[section padding="50px"]
[row h_align="center"]
[col span="8" span__sm="12"]
[ux_text font_size="1.4"]
<h2>Tell Us About Your Business</h2>
[/ux_text]
[ux_text]
<p style="margin-bottom:30px;">Fill out the form below and a member of our wholesale team will reach out to discuss your needs, answer questions, and arrange samples.</p>
[/ux_text]

[contact-form-7 id="242983" title="Request Samples Form"]

[/col]
[/row]
[/section]

[section bg_color="rgb(248,248,248)" padding="40px"]
[row h_align="center"]
[col span="8" span__sm="12"]
[ux_text font_size="1.3" text_align="center"]
<h3>What to Expect</h3>
[/ux_text]
[row_inner]
[col_inner span="4" span__sm="12" align="center"]
<h4>1. We Review</h4>
<p>Our wholesale team reviews your request and verifies your business within 1-2 business days.</p>
[/col_inner]
[col_inner span="4" span__sm="12" align="center"]
<h4>2. We Connect</h4>
<p>A team member will call or email you to discuss which products and configurations are the best fit.</p>
[/col_inner]
[col_inner span="4" span__sm="12" align="center"]
<h4>3. Samples Ship</h4>
<p>Approved brands receive complimentary samples. Otherwise, sample costs are credited toward your first order.</p>
[/col_inner]
[/row_inner]
[/col]
[/row]
[/section]',
        'template' => 'page-blank.php',
        'status' => 'publish',
    ),
    array(
        'slug' => 'ccell-heating-technology',
        'title' => 'CCELL Heating Technology',
        'content' => '',
        'template' => 'default',
        'status' => 'publish',
    ),
    array(
        'slug' => 'snap-fit-capping',
        'title' => 'Snap-Fit Capping Technology',
        'content' => '[section bg_color="rgb(25, 25, 25)" padding="100px" dark="true"]

[row]

[col span="6" span__sm="12"]

<h1><strong style="color: #E50914;">Snap-Fit Capping</strong> Technology</h1>
<p style="font-size: 1.2em; color: #aaa; margin-top: 15px;">Cut your capping force by 5x and accelerate production with pre-racked, snap-fit CCELL&reg; hardware.</p>

[/col]
[col span="6" span__sm="12"]

[ux_image id="243194"]

[/col]

[/row]

[/section]
[section padding="70px"]

[row]

[col span="3" span__sm="6" align="center"]

<h2 style="font-size: 3em; margin-bottom: 0;"><strong>50</strong></h2>
<p style="text-transform: uppercase; letter-spacing: 2px; font-size: 0.85em; color: #666;">Units Per Press</p>

[/col]
[col span="3" span__sm="6" align="center"]

<h2 style="font-size: 3em; margin-bottom: 0;"><strong>5x</strong></h2>
<p style="text-transform: uppercase; letter-spacing: 2px; font-size: 0.85em; color: #666;">Operational Efficiency</p>

[/col]
[col span="3" span__sm="6" align="center"]

<h2 style="font-size: 3em; margin-bottom: 0;"><strong>80%</strong></h2>
<p style="text-transform: uppercase; letter-spacing: 2px; font-size: 0.85em; color: #666;">Reduced Labor Cost</p>

[/col]
[col span="3" span__sm="6" align="center"]

<h2 style="font-size: 3em; margin-bottom: 0;"><strong>20%</strong></h2>
<p style="text-transform: uppercase; letter-spacing: 2px; font-size: 0.85em; color: #666;">Smaller Packaging</p>

[/col]

[/row]

[/section]
[section bg_color="rgb(40, 40, 40)" padding="80px" dark="true"]

[row]

[col span="6" span__sm="12"]

<h2>Higher Output, <strong style="color: #E50914;">Lower Effort</strong></h2>
<p>CCELL\'s snap-fit capping system dramatically reduces the force needed to seat mouthpieces — down to just 25–50 lbf compared to traditional press-fit designs. Combined with pre-racked foam trays, operators can cap 50 units in a single press cycle, cutting labor time and boosting consistency across every run.</p>
<p>Whether you\'re scaling up from hand-capping or optimizing an existing line, snap-fit hardware integrates directly into your current workflow and most major filling platforms.</p>

[/col]
[col span="6" span__sm="12"]

[ux_image id="243193"]

[/col]

[/row]

[/section]
[section bg_color="rgb(245, 245, 245)" padding="80px"]

[row]

[col span="6" span__sm="12"]

[ux_image id="243204"]

[/col]
[col span="6" span__sm="12"]

<h2>Pre-Racked <strong>Foam Trays</strong></h2>
<p>Every snap-fit cartridge and all-in-one ships arranged 5 x 10 in precision-cut foam trays with 14 mm hole-to-hole spacing. Open the box, load the tray onto your filler, and go — no sorting, no individual handling.</p>
<p>The trays work with most major filling equipment right out of the box, so you move from filling to capping to packaging without swapping fixtures or re-racking units.</p>

<h3>Specifications</h3>
<ul>
<li>5 x 10 pre-racked foam trays (50 units per tray)</li>
<li>14 mm hole-to-hole spacing</li>
<li>Cap 50 units per press cycle</li>
<li>5x lower press force (25–50 lbf)</li>
<li>Separate reservoir &amp; mouthpiece trays available</li>
<li>Product-specific fixtures included</li>
<li>CCELL&reg; Arbor Press compatible</li>
</ul>

[/col]

[/row]

[/section]
[section bg_color="rgb(40, 40, 40)" padding="80px" dark="true"]

[row]

[col span="6" span__sm="12"]

<h2>CCELL&reg; <strong style="color: #E50914;">Arbor Press System</strong></h2>
<p>The CCELL&reg; Arbor Press is purpose-built for snap-fit capping. The ergonomic lever design delivers consistent, even force across all 50 units simultaneously — no air compressor, no power supply, no calibration. Just pull the handle.</p>
<p>Product-specific fixtures ensure a perfect fit for each cartridge or all-in-one model, preventing damage and guaranteeing a sealed finish every time.</p>

[/col]
[col span="6" span__sm="12"]

[ux_image id="243202"]

[/col]

[/row]

[/section]
[section padding="60px"]

[row]

[col span="6" span__sm="12" align="center"]

[ux_image id="243203"]

[/col]
[col span="6" span__sm="12" align="center"]

[ux_image id="243205"]

[/col]

[/row]

[/section]
[section bg_color="rgb(40, 40, 40)" padding="80px" dark="true"]

[row]

[col span__sm="12" align="center"]

<h2>Upgraded Production, <strong style="color: #E50914;">Simplified</strong></h2>
<p style="max-width: 800px; margin: 0 auto;">Many of the most popular CCELL&reg; cartridges and all-in-ones are now available with low-force snap-fit mouthpieces, designed to pair with the CCELL&reg; Arbor Press. The result: faster capping, fewer repetitive-strain issues for operators, and a more consistent finished product across every batch.</p>
<p style="max-width: 800px; margin: 0 auto;">Less labor. More consistency. Higher throughput — without adding headcount or equipment.</p>

[/col]

[/row]

[/section]
[section bg_color="rgb(30, 30, 30)" padding="80px" dark="true"]

[row]

[col span__sm="12" align="center"]

<h2>Compatible <strong style="color: #E50914;">Filling Equipment</strong></h2>
<p style="max-width: 700px; margin: 0 auto 40px; color: #aaa;">Snap-fit foam trays integrate with leading automated and semi-automated filling platforms.</p>

[/col]

[/row]

[row]

[col span="4" span__sm="6" align="center" padding="20px"]

[ux_image id="243195"]

[/col]
[col span="4" span__sm="6" align="center" padding="20px"]

[ux_image id="243196"]

[/col]
[col span="4" span__sm="6" align="center" padding="20px"]

[ux_image id="243197"]

[/col]

[/row]

[row]

[col span="2" span__sm="12"]
[/col]
[col span="4" span__sm="6" align="center" padding="20px"]

[ux_image id="243198"]

[/col]
[col span="4" span__sm="6" align="center" padding="20px"]

[ux_image id="243199"]

[/col]
[col span="2" span__sm="12"]
[/col]

[/row]

[/section]
[section bg_color="rgb(40, 40, 40)" padding="80px" dark="true"]

[row]

[col span="5" span__sm="12"]

<h2>Ready to Fill, <strong style="color: #E50914;">Easy to Cap</strong></h2>
<p>Every snap-fit product arrives pre-racked in foam trays — organized, protected, and ready for your production line. Just load, fill, cap, and pack. It\'s the fastest path from raw hardware to finished goods.</p>
<p><a href="/contact/" class="button primary" style="margin-top: 15px;">Contact Sales</a></p>

[/col]
[col span="7" span__sm="12"]

<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 8px;">
<iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" src="https://www.youtube.com/embed/Fd8vFz_XJ6s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen title="Snap-Fit Cartridge Filling and Capping Guide"></iframe>
</div>

[/col]

[/row]

[/section]',
        'template' => 'default',
        'status' => 'publish',
        'parent_slug' => 'technology',
    ),
);

foreach ($pages_to_create as $pg) {
    $existing = get_page_by_path($pg["slug"]);
    if (!$existing && isset($pg["parent_slug"])) {
        $existing = get_page_by_path($pg["parent_slug"] . "/" . $pg["slug"]);
    }
    if ($existing) {
        mlog("  SKIP: {$pg['slug']} already exists (ID: {$existing->ID})");
        // Update content if it changed
        wp_update_post(array(
            "ID" => $existing->ID,
            "post_content" => $pg["content"],
        ));
        if ($pg["template"] !== "default") {
            update_post_meta($existing->ID, "_wp_page_template", $pg["template"]);
        }
        continue;
    }
    
    $post_data = array(
        "post_title"   => $pg["title"],
        "post_name"    => $pg["slug"],
        "post_content" => $pg["content"],
        "post_status"  => $pg["status"],
        "post_type"    => "page",
        "post_author"  => 1,
    );
    
    if (isset($pg["parent_slug"])) {
        $parent = get_page_by_path($pg["parent_slug"]);
        if ($parent) $post_data["post_parent"] = $parent->ID;
    }
    
    $page_id = wp_insert_post($post_data);
    if (is_wp_error($page_id)) {
        mlog("  ERROR: {$pg['slug']}: " . $page_id->get_error_message());
    } else {
        if ($pg["template"] !== "default") {
            update_post_meta($page_id, "_wp_page_template", $pg["template"]);
        }
        mlog("  CREATED: {$pg['slug']} (ID: $page_id)");
    }
}
mlog("");


// ============================================================
// SECTION 3: Image Attachments & ID Mapping
// ============================================================
mlog("--- Section 3: Image Attachments ---");

// Docker attachment ID => file path (for content ID replacement)
$docker_id_to_path = array(
    242761 => '2026/01/1_0000-1.png',
    241685 => '2026/01/1_0000.png',
    242762 => '2026/01/1_0003-1.png',
    241688 => '2026/01/1_0003.png',
    242763 => '2026/01/1_0009-1.png',
    241686 => '2026/01/1_0009.png',
    242764 => '2026/01/1_0013-1.png',
    241689 => '2026/01/1_0013.png',
    242765 => '2026/01/1_0017-1.png',
    241690 => '2026/01/1_0017.png',
    242766 => '2026/01/1_0020-1.png',
    241691 => '2026/01/1_0020.png',
    242767 => '2026/01/1_0024-1.png',
    241692 => '2026/01/1_0024.png',
    242755 => '2026/01/1_0027-1.png',
    241693 => '2026/01/1_0027.png',
    242759 => '2026/01/1_0030-1.png',
    241687 => '2026/01/1_0030.png',
    242768 => '2026/01/1_0034-1.png',
    241694 => '2026/01/1_0034.png',
    242760 => '2026/01/1_0036-1.png',
    241695 => '2026/01/1_0036.png',
    241714 => '2026/01/GemBox-1-scaled.png',
    241723 => '2026/01/GemBox-10-1-scaled.png',
    242758 => '2026/01/GemBox-10-2-scaled.png',
    241697 => '2026/01/GemBox-10-scaled.png',
    241715 => '2026/01/GemBox-2-scaled.png',
    241716 => '2026/01/GemBox-3-scaled.png',
    241717 => '2026/01/GemBox-4-scaled.png',
    241718 => '2026/01/GemBox-5-scaled.png',
    241719 => '2026/01/GemBox-6-scaled.png',
    241720 => '2026/01/GemBox-7-scaled.png',
    241721 => '2026/01/GemBox-8-scaled.png',
    241722 => '2026/01/GemBox-9-1-scaled.png',
    242769 => '2026/01/GemBox-9-2-scaled.png',
    241696 => '2026/01/GemBox-9-scaled.png',
    241558 => '2026/01/Vaporizers-Banner.jpg',
    241559 => '2026/01/Vaporizers-Bannerm.jpg',
    242408 => '2026/02/1.jpg',
    242414 => '2026/02/2.jpg',
    242413 => '2026/02/3.jpg',
    242412 => '2026/02/4.jpg',
    242411 => '2026/02/5.jpg',
    242366 => '2026/02/bl.jpg',
    243010 => '2026/02/ccell-evomax-atomizer-cutaway.png',
    243008 => '2026/02/ccell-evomax-glass-1ml.jpg',
    243009 => '2026/02/ccell-evomax-glass-sizes.jpg',
    243013 => '2026/02/ccell-evomax-th2-lifestyle-2.jpg',
    243014 => '2026/02/ccell-evomax-th2-lifestyle-3.jpg',
    243015 => '2026/02/ccell-evomax-th2-lifestyle-4.jpg',
    243007 => '2026/02/ccell-evomax-th2-official-1.jpg',
    243011 => '2026/02/ccell-evomax-th2-official-3.png',
    243012 => '2026/02/ccell-evomax-th2-official-6.png',
    242409 => '2026/02/checkicon.png',
    242410 => '2026/02/clsoeicon.png',
    242449 => '2026/02/f1.png',
    242450 => '2026/02/f2.png',
    242451 => '2026/02/f3.png',
    242452 => '2026/02/f4.png',
    242770 => '2026/02/Presidents-Day-New.jpg',
    242771 => '2026/02/Presidents-Day-Newmobile.jpg',
    242448 => '2026/02/sa.png',
    242447 => '2026/02/stars.png',
    242631 => '2026/02/Valentine.jpg',
    242632 => '2026/02/Valentinem.jpg',
    242367 => '2026/02/voc.png',
    243389 => '2026/03/airone-launch-page-01.jpg',
    243390 => '2026/03/airone-launch-page-02.jpg',
    243391 => '2026/03/airone-launch-page-03.jpg',
    243392 => '2026/03/airone-launch-page-04.jpg',
    243393 => '2026/03/airone-launch-page-05.jpg',
    243394 => '2026/03/airone-launch-page-06.jpg',
    243395 => '2026/03/airone-launch-page-07.jpg',
    243396 => '2026/03/airone-launch-page-08.jpg',
    243397 => '2026/03/airone-launch-page-09.jpg',
    243398 => '2026/03/airone-launch-page-10.jpg',
    243380 => '2026/03/blade-launch-page-01.jpg',
    243381 => '2026/03/blade-launch-page-02.jpg',
    243382 => '2026/03/blade-launch-page-03.jpg',
    243383 => '2026/03/blade-launch-page-04.jpg',
    243384 => '2026/03/blade-launch-page-05.jpg',
    243385 => '2026/03/blade-launch-page-06.jpg',
    243386 => '2026/03/blade-launch-page-07.jpg',
    243387 => '2026/03/blade-launch-page-08.jpg',
    243388 => '2026/03/blade-launch-page-09.jpg',
    243309 => '2026/03/bravo-launch-page-1.jpg',
    243310 => '2026/03/bravo-launch-page-2.jpg',
    243311 => '2026/03/bravo-launch-page-3.jpg',
    243312 => '2026/03/bravo-launch-page-4.jpg',
    243313 => '2026/03/bravo-launch-page-5.jpg',
    243314 => '2026/03/bravo-launch-page-6.jpg',
    243315 => '2026/03/bravo-launch-page-7.jpg',
    243059 => '2026/03/ccell-airone-aio.jpg',
    243137 => '2026/03/ccell-airone-gallery-01.jpg',
    243138 => '2026/03/ccell-airone-gallery-02.jpg',
    243139 => '2026/03/ccell-airone-gallery-03.jpg',
    243140 => '2026/03/ccell-airone-gallery-04.jpg',
    243141 => '2026/03/ccell-airone-gallery-05.jpg',
    243142 => '2026/03/ccell-airone-gallery-06.jpg',
    243143 => '2026/03/ccell-airone-gallery-07.jpg',
    243144 => '2026/03/ccell-airone-gallery-08.jpg',
    243145 => '2026/03/ccell-airone-gallery-10.jpg',
    243205 => '2026/03/ccell-arbor-press-fixture-pieces.png',
    243203 => '2026/03/ccell-arbor-press-fixture.png',
    243202 => '2026/03/ccell-arbor-press-front.png',
    243193 => '2026/03/ccell-arbor-press-snap-fit-foam-tray.png',
    243194 => '2026/03/ccell-arbor-press.png',
    243053 => '2026/03/ccell-blade-1ml-aio.jpg',
    243094 => '2026/03/ccell-blade-1ml-gallery-02.jpg',
    243095 => '2026/03/ccell-blade-1ml-gallery-03.jpg',
    243096 => '2026/03/ccell-blade-1ml-gallery-04.jpg',
    243097 => '2026/03/ccell-blade-1ml-gallery-05.jpg',
    243098 => '2026/03/ccell-blade-1ml-gallery-10.jpg',
    243099 => '2026/03/ccell-blade-1ml-gallery-11.jpg',
    243054 => '2026/03/ccell-blade-2ml-aio.jpg',
    243100 => '2026/03/ccell-blade-2ml-gallery-01.jpg',
    243101 => '2026/03/ccell-blade-2ml-gallery-02.jpg',
    243102 => '2026/03/ccell-blade-2ml-gallery-03.jpg',
    243103 => '2026/03/ccell-blade-2ml-gallery-04.jpg',
    243104 => '2026/03/ccell-blade-2ml-gallery-05.jpg',
    243105 => '2026/03/ccell-blade-2ml-gallery-06.jpg',
    243106 => '2026/03/ccell-blade-2ml-gallery-07.jpg',
    243107 => '2026/03/ccell-blade-2ml-gallery-08.jpg',
    243108 => '2026/03/ccell-blade-2ml-gallery-09.jpg',
    243109 => '2026/03/ccell-blade-2ml-gallery-10.jpg',
    243235 => '2026/03/ccell-bravo-black-01.png',
    243236 => '2026/03/ccell-bravo-black-02.png',
    243237 => '2026/03/ccell-bravo-black-03.png',
    243238 => '2026/03/ccell-bravo-black-04.png',
    243239 => '2026/03/ccell-bravo-black-05.png',
    243240 => '2026/03/ccell-bravo-black-06.png',
    243241 => '2026/03/ccell-bravo-black-07.png',
    243242 => '2026/03/ccell-bravo-black-08.png',
    243243 => '2026/03/ccell-bravo-black-09.png',
    243244 => '2026/03/ccell-bravo-black-10.png',
    243245 => '2026/03/ccell-bravo-black-11.png',
    243246 => '2026/03/ccell-bravo-black-12.png',
    243247 => '2026/03/ccell-bravo-black-13.png',
    243248 => '2026/03/ccell-bravo-black-14.png',
    243249 => '2026/03/ccell-bravo-black-15.png',
    243294 => '2026/03/ccell-bravo-grey-01.png',
    243295 => '2026/03/ccell-bravo-grey-02.png',
    243296 => '2026/03/ccell-bravo-grey-03.png',
    243297 => '2026/03/ccell-bravo-grey-04.png',
    243298 => '2026/03/ccell-bravo-grey-05.png',
    243299 => '2026/03/ccell-bravo-grey-06.png',
    243300 => '2026/03/ccell-bravo-grey-07.png',
    243301 => '2026/03/ccell-bravo-grey-08.png',
    243302 => '2026/03/ccell-bravo-grey-09.png',
    243303 => '2026/03/ccell-bravo-grey-10.png',
    243304 => '2026/03/ccell-bravo-grey-11.png',
    243305 => '2026/03/ccell-bravo-grey-12.png',
    243306 => '2026/03/ccell-bravo-grey-13.png',
    243307 => '2026/03/ccell-bravo-grey-14.png',
    243316 => '2026/03/ccell-bravo-kv-clean.jpg',
    243308 => '2026/03/ccell-bravo-kv-main-scaled.jpg',
    243317 => '2026/03/ccell-bravo-kv-text.jpg',
    243250 => '2026/03/ccell-bravo-white-01.png',
    243251 => '2026/03/ccell-bravo-white-02.png',
    243252 => '2026/03/ccell-bravo-white-03.png',
    243253 => '2026/03/ccell-bravo-white-04.png',
    243254 => '2026/03/ccell-bravo-white-05.png',
    243255 => '2026/03/ccell-bravo-white-06.png',
    243256 => '2026/03/ccell-bravo-white-07.png',
    243257 => '2026/03/ccell-bravo-white-08.png',
    243258 => '2026/03/ccell-bravo-white-09.png',
    243259 => '2026/03/ccell-bravo-white-10.png',
    243260 => '2026/03/ccell-bravo-white-11.png',
    243261 => '2026/03/ccell-bravo-white-12.png',
    243262 => '2026/03/ccell-bravo-white-13.png',
    243263 => '2026/03/ccell-bravo-white-14.png',
    243206 => '2026/03/ccell-ceramic-evomax-05ml-correct.jpg',
    243176 => '2026/03/ccell-ceramic-evomax-05ml.jpg',
    243180 => '2026/03/ccell-ceramic-evomax-10ml.jpg',
    243181 => '2026/03/ccell-ceramic-evomax-20ml.jpg',
    243178 => '2026/03/ccell-ceramic-evomax-gallery-05ml.jpg',
    243177 => '2026/03/ccell-ceramic-evomax-gallery-1ml.jpg',
    243179 => '2026/03/ccell-ceramic-evomax-gallery-size.jpg',
    243060 => '2026/03/ccell-easy-pod-system.jpg',
    243146 => '2026/03/ccell-easypod-gallery-01.jpg',
    243147 => '2026/03/ccell-easypod-gallery-02.jpg',
    243148 => '2026/03/ccell-easypod-gallery-03.jpg',
    243149 => '2026/03/ccell-easypod-gallery-04.jpg',
    243150 => '2026/03/ccell-easypod-gallery-05.jpg',
    243151 => '2026/03/ccell-easypod-gallery-06.jpg',
    243152 => '2026/03/ccell-easypod-gallery-07.jpg',
    243153 => '2026/03/ccell-easypod-gallery-08.jpg',
    243154 => '2026/03/ccell-easypod-gallery-09.jpg',
    243155 => '2026/03/ccell-easypod-gallery-10.jpg',
    243278 => '2026/03/ccell-evomax-atomizer.png',
    243275 => '2026/03/ccell-evomax-etp-05ml.jpg',
    243274 => '2026/03/ccell-evomax-etp-10ml.jpg',
    243277 => '2026/03/ccell-evomax-etp-main.jpg',
    243276 => '2026/03/ccell-evomax-etp-size.jpg',
    243271 => '2026/03/ccell-evomax-glass-05ml.jpg',
    243270 => '2026/03/ccell-evomax-glass-10ml.jpg',
    243273 => '2026/03/ccell-evomax-glass-main.jpg',
    243272 => '2026/03/ccell-evomax-glass-size.jpg',
    243056 => '2026/03/ccell-flexcell-x-aio.jpg',
    243120 => '2026/03/ccell-flexcellx-gallery-01.jpg',
    243121 => '2026/03/ccell-flexcellx-gallery-02.jpg',
    243122 => '2026/03/ccell-flexcellx-gallery-03.jpg',
    243123 => '2026/03/ccell-flexcellx-gallery-04.jpg',
    243124 => '2026/03/ccell-flexcellx-gallery-05.jpg',
    243125 => '2026/03/ccell-flexcellx-gallery-06.jpg',
    243126 => '2026/03/ccell-flexcellx-gallery-07.jpg',
    243127 => '2026/03/ccell-flexcellx-gallery-08.jpg',
    243128 => '2026/03/ccell-flexcellx-gallery-charging.jpg',
    243057 => '2026/03/ccell-infinity-aio.jpg',
    243129 => '2026/03/ccell-infinity-gallery-01.jpg',
    243130 => '2026/03/ccell-infinity-gallery-02.jpg',
    243131 => '2026/03/ccell-infinity-gallery-03.jpg',
    243063 => '2026/03/ccell-kap-510-power-supply.jpg',
    243161 => '2026/03/ccell-kap-gallery-01.jpg',
    243162 => '2026/03/ccell-kap-gallery-02.jpg',
    243163 => '2026/03/ccell-kap-gallery-03.jpg',
    243164 => '2026/03/ccell-kap-gallery-04.jpg',
    243165 => '2026/03/ccell-kap-gallery-05.jpg',
    243166 => '2026/03/ccell-kap-gallery-06.jpg',
    243167 => '2026/03/ccell-kap-gallery-07.jpg',
    243168 => '2026/03/ccell-kap-gallery-08.jpg',
    243169 => '2026/03/ccell-kap-gallery-09.jpg',
    243170 => '2026/03/ccell-kap-gallery-10.jpg',
    243171 => '2026/03/ccell-kap-gallery-11.jpg',
    243172 => '2026/03/ccell-kap-gallery-12.jpg',
    243062 => '2026/03/ccell-liquid-que-pod.jpg',
    243058 => '2026/03/ccell-liquid-x-glass-aio.jpg',
    243061 => '2026/03/ccell-luster-pro-pod.jpg',
    243156 => '2026/03/ccell-lusterpro-gallery-02.jpg',
    243157 => '2026/03/ccell-lusterpro-gallery-03.jpg',
    243132 => '2026/03/ccell-lxg-gallery-01.jpg',
    243133 => '2026/03/ccell-lxg-gallery-02.jpg',
    243134 => '2026/03/ccell-lxg-gallery-03.jpg',
    243135 => '2026/03/ccell-lxg-gallery-04.jpg',
    243136 => '2026/03/ccell-lxg-gallery-05.jpg',
    243051 => '2026/03/ccell-m4-tiny-battery.jpg',
    243074 => '2026/03/ccell-m4-tiny-gallery-01.jpg',
    243075 => '2026/03/ccell-m4-tiny-gallery-02.jpg',
    243076 => '2026/03/ccell-m4-tiny-gallery-03.jpg',
    243077 => '2026/03/ccell-m4-tiny-gallery-05.jpg',
    243078 => '2026/03/ccell-m4-tiny-gallery-06.jpg',
    243079 => '2026/03/ccell-m4-tiny-gallery-07.jpg',
    243080 => '2026/03/ccell-m4-tiny-gallery-08.jpg',
    243052 => '2026/03/ccell-m4b-pro-battery.jpg',
    243081 => '2026/03/ccell-m4b-pro-gallery-01.jpg',
    243082 => '2026/03/ccell-m4b-pro-gallery-02.jpg',
    243083 => '2026/03/ccell-m4b-pro-gallery-03.jpg',
    243084 => '2026/03/ccell-m4b-pro-gallery-04.jpg',
    243085 => '2026/03/ccell-m4b-pro-gallery-05.jpg',
    243086 => '2026/03/ccell-m4b-pro-gallery-06.jpg',
    243087 => '2026/03/ccell-m4b-pro-gallery-07.jpg',
    243088 => '2026/03/ccell-m4b-pro-gallery-08.jpg',
    243089 => '2026/03/ccell-m4b-pro-gallery-09.jpg',
    243090 => '2026/03/ccell-m4b-pro-gallery-10.jpg',
    243091 => '2026/03/ccell-m4b-pro-gallery-11.jpg',
    243092 => '2026/03/ccell-m4b-pro-gallery-13.jpg',
    243093 => '2026/03/ccell-m4b-pro-gallery-14.jpg',
    243265 => '2026/03/ccell-m6t05-easy-05ml-01.jpg',
    243266 => '2026/03/ccell-m6t05-easy-05ml-02.jpg',
    243267 => '2026/03/ccell-m6t05-easy-05ml-03.jpg',
    243268 => '2026/03/ccell-m6t05-easy-05ml-04.jpg',
    243269 => '2026/03/ccell-m6t05-easy-05ml-05.jpg',
    243183 => '2026/03/ccell-m6t10-easy-1ml-gallery-01.jpg',
    243184 => '2026/03/ccell-m6t10-easy-1ml-gallery-02.jpg',
    243185 => '2026/03/ccell-m6t10-easy-1ml-gallery-03.jpg',
    243186 => '2026/03/ccell-m6t10-easy-1ml-gallery-04.jpg',
    243187 => '2026/03/ccell-m6t10-easy-1ml-gallery-05.jpg',
    243182 => '2026/03/ccell-m6t10-easy-1ml-hero.jpg',
    243219 => '2026/03/ccell-mini-tank-2ml-hero.jpg',
    243221 => '2026/03/ccell-mini-tank-em-1ml-hero.jpg',
    243209 => '2026/03/ccell-mini-tank-gallery-01.jpg',
    243210 => '2026/03/ccell-mini-tank-gallery-02.jpg',
    243211 => '2026/03/ccell-mini-tank-gallery-03.jpg',
    243212 => '2026/03/ccell-mini-tank-gallery-04.jpg',
    243213 => '2026/03/ccell-mini-tank-gallery-05.jpg',
    243214 => '2026/03/ccell-mini-tank-gallery-07.jpg',
    243215 => '2026/03/ccell-mini-tank-gallery-08.jpg',
    243216 => '2026/03/ccell-mini-tank-gallery-09.jpg',
    243217 => '2026/03/ccell-mini-tank-gallery-size.jpg',
    243208 => '2026/03/ccell-mini-tank-se-1ml-hero.jpg',
    243050 => '2026/03/ccell-palm-se-battery.jpg',
    243069 => '2026/03/ccell-palm-se-gallery-01.jpg',
    243070 => '2026/03/ccell-palm-se-gallery-02.jpg',
    243071 => '2026/03/ccell-palm-se-gallery-03.jpg',
    243072 => '2026/03/ccell-palm-se-gallery-04.jpg',
    243073 => '2026/03/ccell-palm-se-gallery-05.jpg',
    243158 => '2026/03/ccell-que-gallery-02.jpg',
    243159 => '2026/03/ccell-que-gallery-03.jpg',
    243160 => '2026/03/ccell-que-gallery-04.jpg',
    243223 => '2026/03/ccell-rosin-bar-1ml-hero.jpg',
    243224 => '2026/03/ccell-rosin-bar-spin-01.jpg',
    243225 => '2026/03/ccell-rosin-bar-spin-02.jpg',
    243226 => '2026/03/ccell-rosin-bar-spin-03.jpg',
    243227 => '2026/03/ccell-rosin-bar-spin-04.jpg',
    243228 => '2026/03/ccell-rosin-bar-spin-05.jpg',
    243229 => '2026/03/ccell-rosin-bar-spin-06.jpg',
    243230 => '2026/03/ccell-rosin-bar-spin-07.jpg',
    243231 => '2026/03/ccell-rosin-bar-spin-08.jpg',
    243232 => '2026/03/ccell-rosin-bar-spin-09.jpg',
    243233 => '2026/03/ccell-rosin-bar-spin-size.jpg',
    243192 => '2026/03/ccell-snap-fit-capping-hero.jpg',
    243195 => '2026/03/ccell-snapfit-compat-atgpharma.png',
    243197 => '2026/03/ccell-snapfit-compat-dds.png',
    243196 => '2026/03/ccell-snapfit-compat-thompson-duke.png',
    243199 => '2026/03/ccell-snapfit-compat-vapejet.png',
    243198 => '2026/03/ccell-snapfit-compat-xylem.png',
    243204 => '2026/03/ccell-snapfit-foam-trays-diagram.png',
    243189 => '2026/03/ccell-th205-easy-05ml-gallery-03.jpg',
    243190 => '2026/03/ccell-th205-easy-05ml-gallery-04.jpg',
    243191 => '2026/03/ccell-th205-easy-05ml-gallery-06.jpg',
    243188 => '2026/03/ccell-th205-easy-05ml-hero.jpg',
    243055 => '2026/03/ccell-turboom-aio.jpg',
    243110 => '2026/03/ccell-turboom-gallery-01.jpg',
    243111 => '2026/03/ccell-turboom-gallery-02.jpg',
    243112 => '2026/03/ccell-turboom-gallery-03.jpg',
    243113 => '2026/03/ccell-turboom-gallery-04.jpg',
    243114 => '2026/03/ccell-turboom-gallery-05.jpg',
    243115 => '2026/03/ccell-turboom-gallery-06.jpg',
    243116 => '2026/03/ccell-turboom-gallery-07.jpg',
    243117 => '2026/03/ccell-turboom-gallery-08.jpg',
    243118 => '2026/03/ccell-turboom-gallery-10.jpg',
    243119 => '2026/03/ccell-turboom-gallery-11.jpg',
    243406 => '2026/03/ceramic-evomax-sellsheet-page-01.jpg',
    243358 => '2026/03/easybar-launch-page-01.jpg',
    243359 => '2026/03/easybar-launch-page-02.jpg',
    243360 => '2026/03/easybar-launch-page-03.jpg',
    243361 => '2026/03/easybar-launch-page-04.jpg',
    243346 => '2026/03/flex-launch-page-01.jpg',
    243347 => '2026/03/flex-launch-page-02.jpg',
    243348 => '2026/03/flex-launch-page-03.jpg',
    243349 => '2026/03/flex-launch-page-04.jpg',
    243350 => '2026/03/flex-launch-page-05.jpg',
    243351 => '2026/03/flex-launch-page-06.jpg',
    243352 => '2026/03/flex-launch-page-07.jpg',
    243353 => '2026/03/flex-launch-page-08.jpg',
    243354 => '2026/03/flex-launch-page-09.jpg',
    243355 => '2026/03/flex-launch-page-10.jpg',
    243356 => '2026/03/flex-launch-page-11.jpg',
    243357 => '2026/03/flex-launch-page-12.jpg',
    243362 => '2026/03/gembar-launch-page-01.jpg',
    243363 => '2026/03/gembar-launch-page-02.jpg',
    243364 => '2026/03/gembar-launch-page-03.jpg',
    243365 => '2026/03/gembar-launch-page-04.jpg',
    243366 => '2026/03/gembar-launch-page-05.jpg',
    243367 => '2026/03/gembar-launch-page-06.jpg',
    243368 => '2026/03/gembar-launch-page-07.jpg',
    243369 => '2026/03/gembar-launch-page-08.jpg',
    243370 => '2026/03/gembar-launch-page-09.jpg',
    243371 => '2026/03/gembox-launch-page-01.jpg',
    243372 => '2026/03/gembox-launch-page-02.jpg',
    243373 => '2026/03/gembox-launch-page-03.jpg',
    243374 => '2026/03/gembox-launch-page-04.jpg',
    243375 => '2026/03/gembox-launch-page-05.jpg',
    243376 => '2026/03/gembox-launch-page-06.jpg',
    243377 => '2026/03/gembox-launch-page-07.jpg',
    243378 => '2026/03/gembox-launch-page-08.jpg',
    243379 => '2026/03/gembox-launch-page-09.jpg',
    243416 => '2026/03/listo-launch-page-01.jpg',
    243417 => '2026/03/listo-launch-page-02.jpg',
    243418 => '2026/03/listo-launch-page-03.jpg',
    243419 => '2026/03/listo-launch-page-04.jpg',
    243420 => '2026/03/listo-launch-page-05.jpg',
    243421 => '2026/03/listo-launch-page-06.jpg',
    243422 => '2026/03/listo-launch-page-07.jpg',
    243423 => '2026/03/listo-launch-page-08.jpg',
    243424 => '2026/03/listo-launch-page-09.jpg',
    243327 => '2026/03/minitank-launch-page-01.jpg',
    243328 => '2026/03/minitank-launch-page-02.jpg',
    243329 => '2026/03/minitank-launch-page-03.jpg',
    243330 => '2026/03/minitank-launch-page-04.jpg',
    243331 => '2026/03/minitank-launch-page-05.jpg',
    243332 => '2026/03/minitank-launch-page-06.jpg',
    243333 => '2026/03/minitank-launch-page-07.jpg',
    243334 => '2026/03/minitank-launch-page-08.jpg',
    243335 => '2026/03/minitank-launch-page-09.jpg',
    243336 => '2026/03/minitank-launch-page-10.jpg',
    243337 => '2026/03/mixjoy-launch-page-01.jpg',
    243338 => '2026/03/mixjoy-launch-page-02.jpg',
    243339 => '2026/03/mixjoy-launch-page-03.jpg',
    243340 => '2026/03/mixjoy-launch-page-04.jpg',
    243341 => '2026/03/mixjoy-launch-page-05.jpg',
    243342 => '2026/03/mixjoy-launch-page-06.jpg',
    243343 => '2026/03/mixjoy-launch-page-07.jpg',
    243344 => '2026/03/mixjoy-launch-page-08.jpg',
    243345 => '2026/03/mixjoy-launch-page-09.jpg',
    243408 => '2026/03/th2se-launch-page-01.jpg',
    243409 => '2026/03/th2se-launch-page-02.jpg',
    243410 => '2026/03/th2se-launch-page-03.jpg',
    243411 => '2026/03/th2se-launch-page-04.jpg',
    243412 => '2026/03/th2se-launch-page-05.jpg',
    243413 => '2026/03/th2se-launch-page-06.jpg',
    243414 => '2026/03/th2se-launch-page-07.jpg',
    243415 => '2026/03/th2se-launch-page-08.jpg',
    243407 => '2026/03/th2se-m6tse-sellsheet-page-01.jpg',
    243399 => '2026/03/turboom-launch-page-01.jpg',
    243400 => '2026/03/turboom-launch-page-02.jpg',
    243401 => '2026/03/turboom-launch-page-03.jpg',
    243402 => '2026/03/turboom-launch-page-04.jpg',
    243403 => '2026/03/turboom-launch-page-05.jpg',
    243404 => '2026/03/turboom-launch-page-06.jpg',
    243405 => '2026/03/turboom-launch-page-07.jpg',
    243318 => '2026/03/voca-launch-page-01.jpg',
    243319 => '2026/03/voca-launch-page-02.jpg',
    243320 => '2026/03/voca-launch-page-03.jpg',
    243321 => '2026/03/voca-launch-page-04.jpg',
    243322 => '2026/03/voca-launch-page-05.jpg',
    243323 => '2026/03/voca-launch-page-06.jpg',
    243324 => '2026/03/voca-launch-page-07.jpg',
    243325 => '2026/03/voca-launch-page-08.jpg',
    243326 => '2026/03/voca-launch-page-09.jpg',
);

// Docker attachment ID => title
$docker_id_to_title = array(
    242761 => '1_0000',
    241685 => '1_0000',
    242762 => '1_0003',
    241688 => '1_0003',
    242763 => '1_0009',
    241686 => '1_0009',
    242764 => '1_0013',
    241689 => '1_0013',
    242765 => '1_0017',
    241690 => '1_0017',
    242766 => '1_0020',
    241691 => '1_0020',
    242767 => '1_0024',
    241692 => '1_0024',
    242755 => '1_0027',
    241693 => '1_0027',
    242759 => '1_0030',
    241687 => '1_0030',
    242768 => '1_0034',
    241694 => '1_0034',
    242760 => '1_0036',
    241695 => '1_0036',
    241714 => 'GemBox 1',
    241723 => 'GemBox 10',
    242758 => 'GemBox 10',
    241697 => 'GemBox 10',
    241715 => 'GemBox 2',
    241716 => 'GemBox 3',
    241717 => 'GemBox 4',
    241718 => 'GemBox 5',
    241719 => 'GemBox 6',
    241720 => 'GemBox 7',
    241721 => 'GemBox 8',
    241722 => 'GemBox 9',
    242769 => 'GemBox 9',
    241696 => 'GemBox 9',
    241558 => 'Vaporizers-Banner',
    241559 => 'Vaporizers-Bannerm',
    242408 => '1',
    242414 => '2',
    242413 => '3',
    242412 => '4',
    242411 => '5',
    242366 => 'bl',
    243010 => 'CCELL EVO MAX Ceramic Atomizer Cutaway',
    243008 => 'CCELL EVO MAX Glass 1.0ml Cartridge',
    243009 => 'CCELL EVO MAX Glass Size Comparison',
    243013 => 'CCELL EVO MAX White Ceramic Oil Compatibility',
    243014 => 'CCELL EVO MAX Atomizer Pore Comparison',
    243015 => 'CCELL EVO MAX Terpene Vaporization Comparison',
    243007 => 'CCELL EVO MAX TH2 White Ceramic Oil Lifestyle',
    243011 => 'CCELL EVO MAX White Ceramic Cartridge Single',
    243012 => 'CCELL EVO MAX White Ceramic Cartridge Duo',
    242409 => 'checkicon',
    242410 => 'clsoeicon',
    242449 => 'f1',
    242450 => 'f2',
    242451 => 'f3',
    242452 => 'f4',
    242770 => 'President\'s-Day-New',
    242771 => 'President\'s-Day-Newmobile',
    242448 => 'sa',
    242447 => 'stars',
    242631 => 'Valentine',
    242632 => 'Valentinem',
    242367 => 'voc',
    243389 => 'Airone Launch File — Page 1',
    243390 => 'Airone Launch File — Page 2',
    243391 => 'Airone Launch File — Page 3',
    243392 => 'Airone Launch File — Page 4',
    243393 => 'Airone Launch File — Page 5',
    243394 => 'Airone Launch File — Page 6',
    243395 => 'Airone Launch File — Page 7',
    243396 => 'Airone Launch File — Page 8',
    243397 => 'Airone Launch File — Page 9',
    243398 => 'Airone Launch File — Page 10',
    243380 => 'Blade Launch File — Page 1',
    243381 => 'Blade Launch File — Page 2',
    243382 => 'Blade Launch File — Page 3',
    243383 => 'Blade Launch File — Page 4',
    243384 => 'Blade Launch File — Page 5',
    243385 => 'Blade Launch File — Page 6',
    243386 => 'Blade Launch File — Page 7',
    243387 => 'Blade Launch File — Page 8',
    243388 => 'Blade Launch File — Page 9',
    243309 => 'Bravo Launch - Cover',
    243310 => 'Bravo Launch - Tactile Grip',
    243311 => 'Bravo Launch - Unique Lines Design',
    243312 => 'Bravo Launch - 3-Level Voltage',
    243313 => 'Bravo Launch - Specs',
    243314 => 'Bravo Launch - OEM Capabilities',
    243315 => 'Bravo Launch - Closing',
    243059 => 'CCELL Airone AIO Disposable',
    243137 => 'CCELL Airone Detail 1',
    243138 => 'CCELL Airone Detail 2',
    243139 => 'CCELL Airone Detail 3',
    243140 => 'CCELL Airone Detail 4',
    243141 => 'CCELL Airone Detail 5',
    243142 => 'CCELL Airone Detail 6',
    243143 => 'CCELL Airone Detail 7',
    243144 => 'CCELL Airone Detail 8',
    243145 => 'CCELL Airone Detail 10',
    243205 => 'CCELL Arbor Press Fixture Components',
    243203 => 'CCELL Arbor Press Capping Fixture',
    243202 => 'CCELL Arbor Press Front View',
    243193 => 'CCELL Arbor Press System with Snap-Fit Foam Tray',
    243194 => 'CCELL Arbor Press',
    243053 => 'CCELL Blade 1.0ml AIO Disposable',
    243094 => 'CCELL Blade 1.0ml Detail 2',
    243095 => 'CCELL Blade 1.0ml Detail 3',
    243096 => 'CCELL Blade 1.0ml Detail 4',
    243097 => 'CCELL Blade 1.0ml Detail 5',
    243098 => 'CCELL Blade 1.0ml Detail 10',
    243099 => 'CCELL Blade 1.0ml Detail 11',
    243054 => 'CCELL Blade 2.0ml AIO Disposable',
    243100 => 'CCELL Blade 2.0ml Detail 1',
    243101 => 'CCELL Blade 2.0ml Detail 2',
    243102 => 'CCELL Blade 2.0ml Detail 3',
    243103 => 'CCELL Blade 2.0ml Detail 4',
    243104 => 'CCELL Blade 2.0ml Detail 5',
    243105 => 'CCELL Blade 2.0ml Detail 6',
    243106 => 'CCELL Blade 2.0ml Detail 7',
    243107 => 'CCELL Blade 2.0ml Detail 8',
    243108 => 'CCELL Blade 2.0ml Detail 9',
    243109 => 'CCELL Blade 2.0ml Detail 10',
    243235 => 'CCELL Bravo Black 1',
    243236 => 'CCELL Bravo Black 2',
    243237 => 'CCELL Bravo Black 3',
    243238 => 'CCELL Bravo Black 4',
    243239 => 'CCELL Bravo Black 5',
    243240 => 'CCELL Bravo Black 6',
    243241 => 'CCELL Bravo Black 7',
    243242 => 'CCELL Bravo Black 8',
    243243 => 'CCELL Bravo Black 9',
    243244 => 'CCELL Bravo Black 10',
    243245 => 'CCELL Bravo Black 11',
    243246 => 'CCELL Bravo Black 12',
    243247 => 'CCELL Bravo Black 13',
    243248 => 'CCELL Bravo Black 14',
    243249 => 'CCELL Bravo Black 15',
    243294 => 'CCELL Bravo Grey Render 1',
    243295 => 'CCELL Bravo Grey Render 2',
    243296 => 'CCELL Bravo Grey Render 3',
    243297 => 'CCELL Bravo Grey Render 4',
    243298 => 'CCELL Bravo Grey Render 5',
    243299 => 'CCELL Bravo Grey Render 6',
    243300 => 'CCELL Bravo Grey Render 7',
    243301 => 'CCELL Bravo Grey Render 8',
    243302 => 'CCELL Bravo Grey Render 9',
    243303 => 'CCELL Bravo Grey Render 10',
    243304 => 'CCELL Bravo Grey Render 11',
    243305 => 'CCELL Bravo Grey Render 12',
    243306 => 'CCELL Bravo Grey Render 13',
    243307 => 'CCELL Bravo Grey Render 14',
    243316 => 'CCELL Bravo KV Clean',
    243308 => 'CCELL Bravo Key Visual',
    243317 => 'CCELL Bravo KV with Text',
    243250 => 'CCELL Bravo White 1',
    243251 => 'CCELL Bravo White 2',
    243252 => 'CCELL Bravo White 3',
    243253 => 'CCELL Bravo White 4',
    243254 => 'CCELL Bravo White 5',
    243255 => 'CCELL Bravo White 6',
    243256 => 'CCELL Bravo White 7',
    243257 => 'CCELL Bravo White 8',
    243258 => 'CCELL Bravo White 9',
    243259 => 'CCELL Bravo White 10',
    243260 => 'CCELL Bravo White 11',
    243261 => 'CCELL Bravo White 12',
    243262 => 'CCELL Bravo White 13',
    243263 => 'CCELL Bravo White 14',
    243206 => 'CCELL Ceramic EVOMAX 0.5ml Cartridge',
    243176 => 'CCELL Ceramic EVOMAX Cartridge',
    243180 => 'CCELL Ceramic EVOMAX Cartridge',
    243181 => 'CCELL Ceramic EVOMAX Cartridge',
    243178 => 'CCELL Ceramic EVOMAX 0.5ml',
    243177 => 'CCELL Ceramic EVOMAX 1.0ml',
    243179 => 'CCELL Ceramic EVOMAX Size Comparison',
    243060 => 'CCELL Easy Pod System',
    243146 => 'CCELL Easy Pod Detail 1',
    243147 => 'CCELL Easy Pod Detail 2',
    243148 => 'CCELL Easy Pod Detail 3',
    243149 => 'CCELL Easy Pod Detail 4',
    243150 => 'CCELL Easy Pod Detail 5',
    243151 => 'CCELL Easy Pod Detail 6',
    243152 => 'CCELL Easy Pod Detail 7',
    243153 => 'CCELL Easy Pod Detail 8',
    243154 => 'CCELL Easy Pod Detail 9',
    243155 => 'CCELL Easy Pod Detail 10',
    243278 => 'CCELL EVO MAX atomizer',
    243275 => 'CCELL EVO MAX etp 05ml hero',
    243274 => 'CCELL EVO MAX etp 10ml hero',
    243277 => 'CCELL EVO MAX etp main',
    243276 => 'CCELL EVO MAX etp size',
    243271 => 'CCELL EVO MAX glass 05ml hero',
    243270 => 'CCELL EVO MAX glass 10ml hero',
    243273 => 'CCELL EVO MAX glass main',
    243272 => 'CCELL EVO MAX glass size',
    243056 => 'CCELL Flexcell X AIO Disposable',
    243120 => 'CCELL Flexcell X Detail 1',
    243121 => 'CCELL Flexcell X Detail 2',
    243122 => 'CCELL Flexcell X Detail 3',
    243123 => 'CCELL Flexcell X Detail 4',
    243124 => 'CCELL Flexcell X Detail 5',
    243125 => 'CCELL Flexcell X Detail 6',
    243126 => 'CCELL Flexcell X Detail 7',
    243127 => 'CCELL Flexcell X Detail 8',
    243128 => 'CCELL Flexcell X Charging',
    243057 => 'CCELL Infinity 1.0ml AIO Disposable',
    243129 => 'CCELL Infinity Detail 1',
    243130 => 'CCELL Infinity Size Comparison',
    243131 => 'CCELL Infinity Charging',
    243063 => 'CCELL Kap 510 Power Supply',
    243161 => 'CCELL Kap Detail 1',
    243162 => 'CCELL Kap Detail 2',
    243163 => 'CCELL Kap Detail 3',
    243164 => 'CCELL Kap Detail 4',
    243165 => 'CCELL Kap Detail 5',
    243166 => 'CCELL Kap Detail 6',
    243167 => 'CCELL Kap Detail 7',
    243168 => 'CCELL Kap Detail 8',
    243169 => 'CCELL Kap Detail 9',
    243170 => 'CCELL Kap Detail 10',
    243171 => 'CCELL Kap Detail 11',
    243172 => 'CCELL Kap Detail 12',
    243062 => 'CCELL Liquid Que Pod System',
    243058 => 'CCELL Liquid X Glass AIO',
    243061 => 'CCELL Luster Pro Pod System',
    243156 => 'CCELL Luster Pro Detail 2',
    243157 => 'CCELL Luster Pro Size',
    243132 => 'CCELL Liquid X Glass 1.0ml',
    243133 => 'CCELL Liquid X Glass Size',
    243134 => 'CCELL Liquid X Glass 0.5ml',
    243135 => 'CCELL Liquid X Glass 0.5ml Size',
    243136 => 'CCELL Liquid X Glass Charging',
    243051 => 'CCELL M4 Tiny 510 Battery',
    243074 => 'CCELL M4 Tiny Detail 1',
    243075 => 'CCELL M4 Tiny Detail 2',
    243076 => 'CCELL M4 Tiny Detail 3',
    243077 => 'CCELL M4 Tiny Detail 5',
    243078 => 'CCELL M4 Tiny Detail 6',
    243079 => 'CCELL M4 Tiny Detail 7',
    243080 => 'CCELL M4 Tiny Detail 8',
    243052 => 'CCELL M4B Pro 510 Battery',
    243081 => 'CCELL M4B Pro Detail 1',
    243082 => 'CCELL M4B Pro Detail 2',
    243083 => 'CCELL M4B Pro Detail 3',
    243084 => 'CCELL M4B Pro Detail 4',
    243085 => 'CCELL M4B Pro Detail 5',
    243086 => 'CCELL M4B Pro Detail 6',
    243087 => 'CCELL M4B Pro Detail 7',
    243088 => 'CCELL M4B Pro Detail 8',
    243089 => 'CCELL M4B Pro Detail 9',
    243090 => 'CCELL M4B Pro Detail 10',
    243091 => 'CCELL M4B Pro Detail 11',
    243092 => 'CCELL M4B Pro Detail 13',
    243093 => 'CCELL M4B Pro Detail 14',
    243265 => 'CCELL M6T05-Easy 0.5ML 1',
    243266 => 'CCELL M6T05-Easy 0.5ML 2',
    243267 => 'CCELL M6T05-Easy 0.5ML 3',
    243268 => 'CCELL M6T05-Easy 0.5ML 4',
    243269 => 'CCELL M6T05-Easy 0.5ML 5',
    243183 => 'CCELL M6T10-Easy 1.0ml - Detail 1',
    243184 => 'CCELL M6T10-Easy 1.0ml - Detail 2',
    243185 => 'CCELL M6T10-Easy 1.0ml - Detail 3',
    243186 => 'CCELL M6T10-Easy 1.0ml - Detail 4',
    243187 => 'CCELL M6T10-Easy 1.0ml - Detail 5',
    243182 => 'CCELL M6T10-Easy 1.0ml ETP Cartridge',
    243219 => 'CCELL Mini Tank 2.0 2.0ml',
    243221 => 'CCELL Mini Tank EM 1.0ml',
    243209 => 'CCELL Mini Tank Front',
    243210 => 'CCELL Mini Tank Side',
    243211 => 'CCELL Mini Tank Angle',
    243212 => 'CCELL Mini Tank Back',
    243213 => 'CCELL Mini Tank USB-C',
    243214 => 'CCELL Mini Tank Top',
    243215 => 'CCELL Mini Tank Detail',
    243216 => 'CCELL Mini Tank Overview',
    243217 => 'CCELL Mini Tank Size Comparison',
    243208 => 'CCELL Mini Tank SE 1.0ml',
    243050 => 'CCELL Palm SE 510 Battery',
    243069 => 'CCELL Palm SE Detail 1',
    243070 => 'CCELL Palm SE Detail 2',
    243071 => 'CCELL Palm SE Detail 3',
    243072 => 'CCELL Palm SE Detail 4',
    243073 => 'CCELL Palm SE Detail 5',
    243158 => 'CCELL Liquid Que Detail 2',
    243159 => 'CCELL Liquid Que Size',
    243160 => 'CCELL Liquid Que Charging',
    243223 => 'CCELL Rosin Bar 1.0ml',
    243224 => 'CCELL Rosin Bar Front',
    243225 => 'CCELL Rosin Bar Side',
    243226 => 'CCELL Rosin Bar Angle',
    243227 => 'CCELL Rosin Bar Back',
    243228 => 'CCELL Rosin Bar Detail',
    243229 => 'CCELL Rosin Bar USB-C',
    243230 => 'CCELL Rosin Bar Top',
    243231 => 'CCELL Rosin Bar Bottom',
    243232 => 'CCELL Rosin Bar Overview',
    243233 => 'CCELL Rosin Bar Size Comparison',
    243192 => 'CCELL Snap-Fit Capping Technology',
    243195 => 'Snap-Fit Compatible - ATG Pharma',
    243197 => 'Snap-Fit Compatible - DDS',
    243196 => 'Snap-Fit Compatible - Thompson Duke',
    243199 => 'Snap-Fit Compatible - VapeJet',
    243198 => 'Snap-Fit Compatible - Xylem',
    243204 => 'CCELL Snap-Fit Foam Trays Diagram',
    243189 => 'CCELL TH205-Easy 0.5ml - Detail 1',
    243190 => 'CCELL TH205-Easy 0.5ml - Detail 2',
    243191 => 'CCELL TH205-Easy 0.5ml - Detail 3',
    243188 => 'CCELL TH205-Easy 0.5ml Glass Cartridge',
    243055 => 'CCELL TurBoom 2.0ml Dual-Core AIO',
    243110 => 'CCELL TurBoom Detail 1',
    243111 => 'CCELL TurBoom Detail 2',
    243112 => 'CCELL TurBoom Detail 3',
    243113 => 'CCELL TurBoom Detail 4',
    243114 => 'CCELL TurBoom Detail 5',
    243115 => 'CCELL TurBoom Detail 6',
    243116 => 'CCELL TurBoom Detail 7',
    243117 => 'CCELL TurBoom Detail 8',
    243118 => 'CCELL TurBoom Detail 10',
    243119 => 'CCELL TurBoom Detail 11',
    243406 => 'Ceramic-EVOMAX Sell Sheet',
    243358 => 'Easy Bar Launch File — Page 1',
    243359 => 'Easy Bar Launch File — Page 2',
    243360 => 'Easy Bar Launch File — Page 3',
    243361 => 'Easy Bar Launch File — Page 4',
    243346 => 'Flex Series Launch File — Page 1',
    243347 => 'Flex Series Launch File — Page 2',
    243348 => 'Flex Series Launch File — Page 3',
    243349 => 'Flex Series Launch File — Page 4',
    243350 => 'Flex Series Launch File — Page 5',
    243351 => 'Flex Series Launch File — Page 6',
    243352 => 'Flex Series Launch File — Page 7',
    243353 => 'Flex Series Launch File — Page 8',
    243354 => 'Flex Series Launch File — Page 9',
    243355 => 'Flex Series Launch File — Page 10',
    243356 => 'Flex Series Launch File — Page 11',
    243357 => 'Flex Series Launch File — Page 12',
    243362 => 'Gem Bar Launch File — Page 1',
    243363 => 'Gem Bar Launch File — Page 2',
    243364 => 'Gem Bar Launch File — Page 3',
    243365 => 'Gem Bar Launch File — Page 4',
    243366 => 'Gem Bar Launch File — Page 5',
    243367 => 'Gem Bar Launch File — Page 6',
    243368 => 'Gem Bar Launch File — Page 7',
    243369 => 'Gem Bar Launch File — Page 8',
    243370 => 'Gem Bar Launch File — Page 9',
    243371 => 'Gem Box Launch File — Page 1',
    243372 => 'Gem Box Launch File — Page 2',
    243373 => 'Gem Box Launch File — Page 3',
    243374 => 'Gem Box Launch File — Page 4',
    243375 => 'Gem Box Launch File — Page 5',
    243376 => 'Gem Box Launch File — Page 6',
    243377 => 'Gem Box Launch File — Page 7',
    243378 => 'Gem Box Launch File — Page 8',
    243379 => 'Gem Box Launch File — Page 9',
    243416 => 'Listo Launch File — Page 1',
    243417 => 'Listo Launch File — Page 2',
    243418 => 'Listo Launch File — Page 3',
    243419 => 'Listo Launch File — Page 4',
    243420 => 'Listo Launch File — Page 5',
    243421 => 'Listo Launch File — Page 6',
    243422 => 'Listo Launch File — Page 7',
    243423 => 'Listo Launch File — Page 8',
    243424 => 'Listo Launch File — Page 9',
    243327 => 'Mini Tank Launch File — Page 1',
    243328 => 'Mini Tank Launch File — Page 2',
    243329 => 'Mini Tank Launch File — Page 3',
    243330 => 'Mini Tank Launch File — Page 4',
    243331 => 'Mini Tank Launch File — Page 5',
    243332 => 'Mini Tank Launch File — Page 6',
    243333 => 'Mini Tank Launch File — Page 7',
    243334 => 'Mini Tank Launch File — Page 8',
    243335 => 'Mini Tank Launch File — Page 9',
    243336 => 'Mini Tank Launch File — Page 10',
    243337 => 'MixJoy Launch File — Page 1',
    243338 => 'MixJoy Launch File — Page 2',
    243339 => 'MixJoy Launch File — Page 3',
    243340 => 'MixJoy Launch File — Page 4',
    243341 => 'MixJoy Launch File — Page 5',
    243342 => 'MixJoy Launch File — Page 6',
    243343 => 'MixJoy Launch File — Page 7',
    243344 => 'MixJoy Launch File — Page 8',
    243345 => 'MixJoy Launch File — Page 9',
    243408 => 'TH2-SE Launch File — Page 1',
    243409 => 'TH2-SE Launch File — Page 2',
    243410 => 'TH2-SE Launch File — Page 3',
    243411 => 'TH2-SE Launch File — Page 4',
    243412 => 'TH2-SE Launch File — Page 5',
    243413 => 'TH2-SE Launch File — Page 6',
    243414 => 'TH2-SE Launch File — Page 7',
    243415 => 'TH2-SE Launch File — Page 8',
    243407 => 'TH2-SE M6T-SE Sell Sheet',
    243399 => 'TurBoom Launch File — Page 1',
    243400 => 'TurBoom Launch File — Page 2',
    243401 => 'TurBoom Launch File — Page 3',
    243402 => 'TurBoom Launch File — Page 4',
    243403 => 'TurBoom Launch File — Page 5',
    243404 => 'TurBoom Launch File — Page 6',
    243405 => 'TurBoom Launch File — Page 7',
    243318 => 'Voca Launch File — Page 1',
    243319 => 'Voca Launch File — Page 2',
    243320 => 'Voca Launch File — Page 3',
    243321 => 'Voca Launch File — Page 4',
    243322 => 'Voca Launch File — Page 5',
    243323 => 'Voca Launch File — Page 6',
    243324 => 'Voca Launch File — Page 7',
    243325 => 'Voca Launch File — Page 8',
    243326 => 'Voca Launch File — Page 9',
);

// Create all attachments and build mapping
$docker_to_prod_id = array(); // Docker ID => Production ID
$path_to_prod_id = array();   // file path => Production ID

$total_atts = count($docker_id_to_path);
$created_atts = 0;
$skipped_atts = 0;

foreach ($docker_id_to_path as $docker_id => $file_path) {
    $title = isset($docker_id_to_title[$docker_id]) ? $docker_id_to_title[$docker_id] : "";
    $new_id = hd_create_attachment($file_path, $title);
    if ($new_id > 0) {
        $docker_to_prod_id[$docker_id] = $new_id;
        $path_to_prod_id[$file_path] = $new_id;
        if ($new_id != $docker_id) $created_atts++;
        else $skipped_atts++;
    }
}

mlog("  Attachments processed: $total_atts (created: $created_atts, existing: $skipped_atts)");
mlog("");


// ============================================================
// SECTION 4: New Products (41 products)
// ============================================================
mlog("--- Section 4: New Products ---");

$new_products = array(
    array(
        'slug' => 'ccell-airone-aio-disposable',
        'title' => 'CCELL Airone — All-In-One Disposable | 3.0 Bio-Heating',
        'content' => 'The CCELL Airone is a refined, ultra-thin all-in-one disposable featuring CCELL 3.0 Bio-Heating with VeinMesh design. A crystal-clear viewing window, dual voltage settings, and clean airflow channels deliver precision performance.

The cotton-free 3D Stomata ceramic core ensures even heating and consistent oil distribution, while the postless reservoir design maximizes oil visibility.

<strong>Key Features:</strong>
<ul>
<li>1.0ml or 2.0ml dual-capacity options</li>
<li>CCELL 3.0 Bio-Heating with VeinMesh design</li>
<li>3D Stomata ceramic core</li>
<li>210mAh battery</li>
<li>USB-C charging</li>
<li>Inhale-activated</li>
<li>Dual voltage: Low 3.0V / High 3.2V (bottom toggle)</li>
<li>Ultra-thin profile</li>
<li>Transparent viewing window</li>
<li>Dual air vents</li>
<li>Cotton-free core, postless reservoir</li>
<li>ETP material, snap-fit closure</li>
<li>LED indicator</li>
<li>Dimensions (1.0ml): 70.5 x 42.8 x 9.8 mm</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243389" image_size="original" width="100"]
[ux_image id="243390" image_size="original" width="100"]
[ux_image id="243391" image_size="original" width="100"]
[ux_image id="243392" image_size="original" width="100"]
[ux_image id="243393" image_size="original" width="100"]
[ux_image id="243394" image_size="original" width="100"]
[ux_image id="243395" image_size="original" width="100"]
[ux_image id="243396" image_size="original" width="100"]
[ux_image id="243397" image_size="original" width="100"]
[ux_image id="243398" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'CCELL Airone AIO disposable with 3.0 Bio-Heating. Ultra-thin, dual voltage, VeinMesh + Stomata Core, USB-C.',
        'categories' => array (
  0 => 'aio-3-bio-heating',
  1 => 'ccell',
  2 => 'disposable',
),
        'thumbnail' => '2026/03/ccell-airone-aio.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-airone-gallery-01.jpg',
  1 => '2026/03/ccell-airone-gallery-02.jpg',
  2 => '2026/03/ccell-airone-gallery-03.jpg',
  3 => '2026/03/ccell-airone-gallery-04.jpg',
  4 => '2026/03/ccell-airone-gallery-05.jpg',
  5 => '2026/03/ccell-airone-gallery-06.jpg',
  6 => '2026/03/ccell-airone-gallery-07.jpg',
  7 => '2026/03/ccell-airone-gallery-08.jpg',
  8 => '2026/03/ccell-airone-gallery-10.jpg',
),
        'meta' => array(
            '_sku' => 'AIRONE-AIO-30',
            '_regular_price' => '9.99',
            '_price' => '9.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '5.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '5.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$9.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$8.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$7.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$6.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$5.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$6.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$5.99',
            'wholesale_customer_wholesale_price' => '6.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '6.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '5.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Airone all-in-one disposable with 3.0 Bio-Heating. Ultra-thin, dual voltage. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-blade-1-0ml-aio-disposable',
        'title' => 'CCELL Blade — 1.0ml All-In-One Disposable | 3.0 Bio-Heating',
        'content' => 'The CCELL Blade is a next-generation all-in-one disposable engineered for premium oil performance in an ultra-slim form factor. Featuring CCELL 3.0 Bio-Heating with VeinMesh design and 3D Stomata ceramic core, the Blade delivers smooth, flavorful vapor with 30% lower atomization temperatures to preserve terpenes.

The cotton-free core and postless reservoir design provide maximum oil visibility through a 360-degree viewing window, while dual air vents optimize airflow for consistent draws.

<strong>Key Features:</strong>
<ul>
<li>1.0ml reservoir capacity</li>
<li>CCELL 3.0 Bio-Heating with VeinMesh design</li>
<li>3D Stomata ceramic core — 10x more consistent micropores</li>
<li>230mAh rechargeable battery</li>
<li>USB-C charging</li>
<li>Inhale-activated</li>
<li>Ultra-slim form factor (88.6 x 28 x 10.2 mm)</li>
<li>360-degree oil visibility window</li>
<li>Dual air vents</li>
<li>100% cotton-free core</li>
<li>Postless reservoir design</li>
<li>BPA-free PA material</li>
<li>Snap-fit closure</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Heating: CCELL 3.0 Bio-Heating (VeinMesh + Stomata Core)</li>
<li>Capacity: 1.0ml</li>
<li>Battery: 230mAh</li>
<li>Charging: USB-C</li>
<li>Material: BPA-Free PA</li>
<li>Activation: Inhale-activated</li>
<li>Viscosity Range: 700,000 – 6,000,000 cP</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243380" image_size="original" width="100"]
[ux_image id="243381" image_size="original" width="100"]
[ux_image id="243382" image_size="original" width="100"]
[ux_image id="243383" image_size="original" width="100"]
[ux_image id="243384" image_size="original" width="100"]
[ux_image id="243385" image_size="original" width="100"]
[ux_image id="243386" image_size="original" width="100"]
[ux_image id="243387" image_size="original" width="100"]
[ux_image id="243388" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'CCELL Blade 1.0ml AIO disposable with 3.0 Bio-Heating. Ultra-slim, VeinMesh element, 360-degree oil visibility, USB-C rechargeable.',
        'categories' => array (
  0 => 'aio-3-bio-heating',
  1 => 'ccell',
  2 => 'disposable',
),
        'thumbnail' => '2026/03/ccell-blade-1ml-aio.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-blade-1ml-gallery-02.jpg',
  1 => '2026/03/ccell-blade-1ml-gallery-03.jpg',
  2 => '2026/03/ccell-blade-1ml-gallery-04.jpg',
  3 => '2026/03/ccell-blade-1ml-gallery-05.jpg',
  4 => '2026/03/ccell-blade-1ml-gallery-10.jpg',
  5 => '2026/03/ccell-blade-1ml-gallery-11.jpg',
),
        'meta' => array(
            '_sku' => 'BLADE-10-AIO',
            '_regular_price' => '9.99',
            '_price' => '9.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '5.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '5.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$9.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$8.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$7.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$6.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$5.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$6.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$5.99',
            'wholesale_customer_wholesale_price' => '6.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '6.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '5.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Blade 1.0ml all-in-one disposable with 3.0 Bio-Heating. Ultra-slim, VeinMesh + Stomata Core. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-blade-2-0ml-aio-disposable',
        'title' => 'CCELL Blade — 2.0ml All-In-One Disposable | 3.0 Bio-Heating',
        'content' => 'The CCELL Blade 2.0ml is a next-generation all-in-one disposable with CCELL 3.0 Bio-Heating technology. The larger 2.0ml capacity and ultra-slim form factor (92.3 x 30 x 12.5 mm) make it ideal for brands offering larger-format disposables.

<strong>Key Features:</strong>
<ul>
<li>2.0ml reservoir capacity</li>
<li>CCELL 3.0 Bio-Heating with VeinMesh design</li>
<li>3D Stomata ceramic core</li>
<li>230mAh rechargeable battery</li>
<li>USB-C charging</li>
<li>Inhale-activated</li>
<li>360-degree oil visibility window</li>
<li>Dual air vents</li>
<li>100% cotton-free core, postless reservoir</li>
<li>BPA-free PA material, snap-fit closure</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243380" image_size="original" width="100"]
[ux_image id="243381" image_size="original" width="100"]
[ux_image id="243382" image_size="original" width="100"]
[ux_image id="243383" image_size="original" width="100"]
[ux_image id="243384" image_size="original" width="100"]
[ux_image id="243385" image_size="original" width="100"]
[ux_image id="243386" image_size="original" width="100"]
[ux_image id="243387" image_size="original" width="100"]
[ux_image id="243388" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'CCELL Blade 2.0ml AIO disposable with 3.0 Bio-Heating. Ultra-slim, VeinMesh + Stomata Core, USB-C.',
        'categories' => array (
  0 => 'aio-3-bio-heating',
  1 => 'ccell',
  2 => 'disposable',
),
        'thumbnail' => '2026/03/ccell-blade-2ml-aio.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-blade-2ml-gallery-01.jpg',
  1 => '2026/03/ccell-blade-2ml-gallery-02.jpg',
  2 => '2026/03/ccell-blade-2ml-gallery-03.jpg',
  3 => '2026/03/ccell-blade-2ml-gallery-04.jpg',
  4 => '2026/03/ccell-blade-2ml-gallery-05.jpg',
  5 => '2026/03/ccell-blade-2ml-gallery-06.jpg',
  6 => '2026/03/ccell-blade-2ml-gallery-07.jpg',
  7 => '2026/03/ccell-blade-2ml-gallery-08.jpg',
  8 => '2026/03/ccell-blade-2ml-gallery-09.jpg',
  9 => '2026/03/ccell-blade-2ml-gallery-10.jpg',
),
        'meta' => array(
            '_sku' => 'BLADE-20-AIO',
            '_regular_price' => '9.99',
            '_price' => '9.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '5.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '5.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$9.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$8.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$7.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$6.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$5.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$6.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$5.99',
            'wholesale_customer_wholesale_price' => '6.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '6.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '5.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Blade 2.0ml all-in-one disposable with 3.0 Bio-Heating. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-bravo-2ml-aio-evomax',
        'title' => 'CCELL Bravo — 2.0ml All-In-One | EVOMAX',
        'content' => 'The CCELL Bravo is a premium 2.0ml all-in-one disposable featuring the EVOMAX ceramic heating core and a bold, contoured design built for true hand-fit comfort. With 3-level voltage settings, a preheat function, and a 290mAh USB-C rechargeable battery, the Bravo gives consumers complete control over their session — from flavor-focused draws to cloud-chasing hits.

Defined by elegant vertical grooves flowing into a unified U-shaped profile, the Bravo\'s minimalist and tactile design makes a statement while its contoured curves prevent drops during active use. The BPA-free PA tank provides full oil visibility, and the EVOMAX heating core handles every oil type with zero clogging or leaking.

<strong>Key Features:</strong>
<ul>
<li>2.0ml capacity BPA-free PA tank with full oil visibility</li>
<li>CCELL EVOMAX ceramic heating core</li>
<li>3-level voltage settings (2.8V / 3.0V / 3.2V)</li>
<li>Preheat function for thick oils</li>
<li>290mAh rechargeable battery (USB-C)</li>
<li>Inhale-activated — no buttons needed for draw</li>
<li>Contoured ergonomic body — tactile grip, true hand-fit</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Fully customizable: colors, finishes, silkscreen/laser engraving, gradient spraying</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 2.0ml</li>
<li>Heating: CCELL EVOMAX ceramic core</li>
<li>Battery: 290mAh (USB-C charging)</li>
<li>Voltage: 3 settings — 2.8V / 3.0V / 3.2V</li>
<li>Activation: Inhale-activated + preheat</li>
<li>Body: BPA-free PA (injection molding, spray coating)</li>
<li>Mouthpiece: Injection molded</li>
<li>Dimensions: 66.4H x 30W x 15D mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>

<div style="margin: 40px 0; padding: 0;">
<h3 style="text-align:center; margin-bottom: 20px; font-size: 1.4em; letter-spacing: 1px; text-transform: uppercase; color: #333;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243309" image_size="original" width="100"]
[ux_image id="243310" image_size="original" width="100"]
[ux_image id="243311" image_size="original" width="100"]
[ux_image id="243312" image_size="original" width="100"]
[ux_image id="243313" image_size="original" width="100"]
[ux_image id="243314" image_size="original" width="100"]
[/ux_slider]
</div>',
        'excerpt' => 'CCELL Bravo 2.0ml all-in-one with EVOMAX heating, 3-level voltage control, preheat function, 290mAh USB-C battery. Contoured ergonomic design with tactile grip.',
        'categories' => array (
  0 => 'aio-evo-max',
  1 => 'ccell',
  2 => 'disposable',
),
        'thumbnail' => '2026/03/ccell-bravo-grey-01.png',
        'gallery' => array (
),
        'meta' => array(
            '_sku' => 'BRAVO-20-AIO',
            '_regular_price' => '9.99',
            '_price' => '9.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '5.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '5.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$9.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$8.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$7.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$6.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$5.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$6.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$5.99',
            'wholesale_customer_wholesale_price' => '6.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '6.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '5.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Bravo 2.0ml all-in-one with EVOMAX heating core, 3-level voltage, preheat, 290mAh USB-C. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-ceramic-evomax-0-5ml-cartridge',
        'title' => 'CCELL Ceramic EVOMAX — 0.5ml All-Ceramic Cartridge',
        'content' => 'The CCELL Ceramic EVOMAX is an all-ceramic 510-thread cartridge featuring the EVOMAX oversized ceramic heating element with thicker walls for superior heat distribution. The full ceramic body and ceramic airway eliminate all metal contact with oil, delivering the purest flavor from any formulation.

Built for potency and durability, the Ceramic EVOMAX handles thick extracts across the full viscosity spectrum — from distillates to live rosins and liquid diamonds — providing rich, consistent vapor without burning out.

The 0.5ml borosilicate glass reservoir provides full oil visibility while the snap-fit closure ensures a secure, tamper-resistant seal.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity with borosilicate glass reservoir</li>
<li>EVOMAX oversized ceramic heating element</li>
<li>Full ceramic body — zero metal oil contact</li>
<li>Ceramic airway</li>
<li>1.7&#8486; resistance</li>
<li>510 thread connection</li>
<li>Snap-fit closure</li>
<li>4 x &#248;2mm aperture inlets</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 0.5ml</li>
<li>Body: Full ceramic</li>
<li>Reservoir: Borosilicate glass</li>
<li>Airway: Ceramic</li>
<li>Heating: EVOMAX oversized ceramic</li>
<li>Resistance: 1.7&#8486;</li>
<li>Closure: Snap-fit</li>
<li>Thread: 510</li>
<li>Dimensions: 52H x 11W x 11D mm</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_image id="243406" image_size="original" width="100"]',
        'excerpt' => 'CCELL Ceramic EVOMAX 0.5ml all-ceramic cartridge with EVOMAX oversized heating. Zero metal oil contact, ceramic airway. 1.7 ohm, 510 thread.',
        'categories' => array (
  0 => 'ccell-ceramic-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-ceramic-evomax-05ml-correct.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-ceramic-evomax-gallery-1ml.jpg',
  1 => '2026/03/ccell-ceramic-evomax-gallery-05ml.jpg',
  2 => '2026/03/ccell-ceramic-evomax-gallery-size.jpg',
),
        'meta' => array(
            '_sku' => 'CER-EMX-05',
            '_regular_price' => '5.99',
            '_price' => '5.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$5.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.99',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.99',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$3.49',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$3.49',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.99',
            'wholesale_customer_wholesale_price' => '3.49',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '3.49',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Ceramic EVOMAX 0.5ml all-ceramic cartridge with EVOMAX heating. Zero metal contact, ceramic airway. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-ceramic-evomax-1-0ml-cartridge',
        'title' => 'CCELL Ceramic EVOMAX — 1.0ml All-Ceramic Cartridge',
        'content' => 'The CCELL Ceramic EVOMAX 1.0ml is an all-ceramic 510-thread cartridge featuring the EVOMAX oversized ceramic heating element. The full ceramic body and ceramic airway eliminate all metal contact with oil for the purest possible flavor profile.

The full-gram capacity makes this ideal for brands offering larger products while maintaining premium all-ceramic construction and EVOMAX performance across the full viscosity spectrum.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity with borosilicate glass reservoir</li>
<li>EVOMAX oversized ceramic heating element</li>
<li>Full ceramic body — zero metal oil contact</li>
<li>Ceramic airway</li>
<li>1.7&#8486; resistance</li>
<li>510 thread connection</li>
<li>Snap-fit closure</li>
<li>4 x &#248;2mm aperture inlets</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: Full ceramic</li>
<li>Reservoir: Borosilicate glass</li>
<li>Airway: Ceramic</li>
<li>Heating: EVOMAX oversized ceramic</li>
<li>Resistance: 1.7&#8486;</li>
<li>Closure: Snap-fit</li>
<li>Thread: 510</li>
<li>Dimensions: 62H x 11W x 11D mm</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_image id="243406" image_size="original" width="100"]',
        'excerpt' => 'CCELL Ceramic EVOMAX 1.0ml all-ceramic cartridge with EVOMAX oversized heating. Zero metal oil contact, ceramic airway. 1.7 ohm, 510 thread.',
        'categories' => array (
  0 => 'ccell-ceramic-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-ceramic-evomax-10ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-ceramic-evomax-gallery-1ml.jpg',
  1 => '2026/03/ccell-ceramic-evomax-gallery-05ml.jpg',
  2 => '2026/03/ccell-ceramic-evomax-gallery-size.jpg',
),
        'meta' => array(
            '_sku' => 'CER-EMX-10',
            '_regular_price' => '5.99',
            '_price' => '5.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$5.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.99',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.99',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$3.49',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$3.49',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.99',
            'wholesale_customer_wholesale_price' => '3.49',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '3.49',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Ceramic EVOMAX 1.0ml all-ceramic cartridge with EVOMAX heating. Zero metal contact, ceramic airway. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-ceramic-evomax-2-0ml-cartridge',
        'title' => 'CCELL Ceramic EVOMAX — 2.0ml All-Ceramic Cartridge',
        'content' => 'The CCELL Ceramic EVOMAX 2.0ml is the largest all-ceramic 510-thread cartridge in the CCELL lineup. Featuring the EVOMAX oversized ceramic heating element with full ceramic body and ceramic airway — zero metal touches your oil from fill port to mouthpiece.

The 2.0ml capacity is ideal for brands offering high-volume products with premium all-ceramic construction. Same EVOMAX performance across the full viscosity spectrum.

<strong>Key Features:</strong>
<ul>
<li>2.0ml capacity with borosilicate glass reservoir</li>
<li>EVOMAX oversized ceramic heating element</li>
<li>Full ceramic body — zero metal oil contact</li>
<li>Ceramic airway</li>
<li>1.7&#8486; resistance</li>
<li>510 thread connection</li>
<li>Snap-fit closure</li>
<li>4 x &#248;2mm aperture inlets</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 2.0ml</li>
<li>Body: Full ceramic</li>
<li>Reservoir: Borosilicate glass</li>
<li>Airway: Ceramic</li>
<li>Heating: EVOMAX oversized ceramic</li>
<li>Resistance: 1.7&#8486;</li>
<li>Closure: Snap-fit</li>
<li>Thread: 510</li>
<li>Dimensions: 66H x 11W x 11D mm</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_image id="243406" image_size="original" width="100"]',
        'excerpt' => 'CCELL Ceramic EVOMAX 2.0ml all-ceramic cartridge with EVOMAX oversized heating. Largest ceramic cartridge — zero metal oil contact. 1.7 ohm.',
        'categories' => array (
  0 => 'ccell-ceramic-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-ceramic-evomax-20ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-ceramic-evomax-gallery-1ml.jpg',
  1 => '2026/03/ccell-ceramic-evomax-gallery-05ml.jpg',
  2 => '2026/03/ccell-ceramic-evomax-gallery-size.jpg',
),
        'meta' => array(
            '_sku' => 'CER-EMX-20',
            '_regular_price' => '5.99',
            '_price' => '5.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$5.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.99',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.99',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$3.49',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$3.49',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.99',
            'wholesale_customer_wholesale_price' => '3.49',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '3.49',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Ceramic EVOMAX 2.0ml all-ceramic cartridge with EVOMAX heating. Zero metal contact. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-ds01-pen-style-aio-disposable',
        'title' => 'CCELL DS01 — Classic Pen-Style All-In-One | SE/EVOMAX',
        'content' => 'The CCELL DS01 Glass (DS01) is a classic pen-style all-in-one disposable combining a visible glass oil tank with stainless steel housing. Available in 0.5ml and 1.0ml with your choice of SE or EVOMAX atomizer.

Medical-grade 316L stainless steel internals and a food-grade PCTG mouthpiece deliver a smooth, true-to-plant experience.

<strong>Key Features:</strong>
<ul>
<li>0.5ml or 1.0ml capacity</li>
<li>SE or EVOMAX atomizer options</li>
<li>Glass tank with stainless steel housing</li>
<li>Medical-grade 316L stainless steel internals</li>
<li>Food-grade PCTG mouthpiece</li>
<li>135mAh (0.5ml) or 330mAh (1.0ml) battery</li>
<li>USB-C charging</li>
<li>Inhale-activated</li>
<li>LED light tip</li>
<li>Visible oil tank</li>
<li>Dimensions: 99 x 10.5 x 10.5 mm</li>
</ul>',
        'excerpt' => 'CCELL DS01 Glass pen-style AIO with SE or EVOMAX atomizer. Glass tank, stainless steel housing, USB-C.',
        'categories' => array (
  0 => 'aio-evo-max',
  1 => 'ccell',
  2 => 'disposable',
),
        'thumbnail' => '2026/03/ccell-liquid-x-glass-aio.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-lxg-gallery-01.jpg',
  1 => '2026/03/ccell-lxg-gallery-02.jpg',
  2 => '2026/03/ccell-lxg-gallery-03.jpg',
  3 => '2026/03/ccell-lxg-gallery-04.jpg',
  4 => '2026/03/ccell-lxg-gallery-05.jpg',
),
        'meta' => array(
            '_sku' => 'DS01-AIO',
            '_regular_price' => '9.99',
            '_price' => '9.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '5.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '5.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$9.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$8.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$7.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$6.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$5.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$6.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$5.99',
            'wholesale_customer_wholesale_price' => '6.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '6.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '5.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL DS01 Glass (DS01) pen-style all-in-one disposable. SE or EVOMAX atomizer. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-easy-pod-system',
        'title' => 'CCELL Easy Pod — Pod System | EVOMAX Atomizer',
        'content' => 'The CCELL Easy Pod is CCELL\'s most affordable pod system, featuring the EVOMAX Atomizer built to vaporize thick, high-viscosity extracts. A magnetic drop-in pod cartridge connects to the rechargeable power supply for easy cartridge swaps.

The EVOMAX platform in the Easy Pod handles live rosins and liquid diamonds with a viscosity range of 700,000–2,000,000 cP (up to 5,000,000 cP with enlarged airway option).

<strong>Key Features:</strong>
<ul>
<li>0.5ml or 1.0ml pod cartridge options</li>
<li>CCELL EVOMAX Atomizer Platform</li>
<li>265mAh rechargeable battery</li>
<li>USB-C charging</li>
<li>Inhale-activated</li>
<li>Magnetic drop-in pod cartridge</li>
<li>Customizable finishes</li>
<li>Dual air vents</li>
<li>LED indicator</li>
<li>Viscosity range: 700,000 – 2,000,000 cP (5,000,000 cP with enlarged airway)</li>
</ul>',
        'excerpt' => 'CCELL Easy Pod system with EVOMAX Atomizer. Magnetic drop-in pods, 265mAh USB-C battery, handles live rosins and liquid diamonds.',
        'categories' => array (
  0 => 'ccell',
  1 => 'pod-systems',
),
        'thumbnail' => '2026/03/ccell-easy-pod-system.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-easypod-gallery-01.jpg',
  1 => '2026/03/ccell-easypod-gallery-02.jpg',
  2 => '2026/03/ccell-easypod-gallery-03.jpg',
  3 => '2026/03/ccell-easypod-gallery-04.jpg',
  4 => '2026/03/ccell-easypod-gallery-05.jpg',
  5 => '2026/03/ccell-easypod-gallery-06.jpg',
  6 => '2026/03/ccell-easypod-gallery-07.jpg',
  7 => '2026/03/ccell-easypod-gallery-08.jpg',
  8 => '2026/03/ccell-easypod-gallery-09.jpg',
  9 => '2026/03/ccell-easypod-gallery-10.jpg',
),
        'meta' => array(
            '_sku' => 'EASYPOD-POD-EM',
            '_regular_price' => '12.99',
            '_price' => '12.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '7.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '7.49',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$12.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$10.99',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$9.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$8.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$7.49',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$8.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$7.49',
            'wholesale_customer_wholesale_price' => '8.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '8.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '7.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Easy Pod system with EVOMAX Atomizer. Magnetic pods, USB-C, high-viscosity support. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-flexcell-x-aio-disposable',
        'title' => 'CCELL Flexcell X — All-In-One Disposable | EVOMAX',
        'content' => 'The CCELL Flexcell X is a clog-free all-in-one disposable featuring the EVOMAX ceramic core. Available in 0.5ml and 1.0-2.0ml capacities, it delivers unmatched flavor and compatibility from distillates to live rosin.

An upgrade from the original Flexcell, the X version features enhanced clog resistance and smoother vapor delivery through the EVOMAX oversized ceramic element.

<strong>Key Features:</strong>
<ul>
<li>0.5ml or 1.0-2.0ml dual-capacity options</li>
<li>CCELL EVOMAX ceramic core</li>
<li>280mAh rechargeable battery</li>
<li>USB-C charging</li>
<li>Inhale-activated</li>
<li>Advanced clog resistance</li>
<li>Customizable window</li>
<li>Dual air vents</li>
<li>ETP (Engineering ThermoPlastic) body</li>
<li>Dimensions: 68.4 x 38 x 19 mm</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243346" image_size="original" width="100"]
[ux_image id="243347" image_size="original" width="100"]
[ux_image id="243348" image_size="original" width="100"]
[ux_image id="243349" image_size="original" width="100"]
[ux_image id="243350" image_size="original" width="100"]
[ux_image id="243351" image_size="original" width="100"]
[ux_image id="243352" image_size="original" width="100"]
[ux_image id="243353" image_size="original" width="100"]
[ux_image id="243354" image_size="original" width="100"]
[ux_image id="243355" image_size="original" width="100"]
[ux_image id="243356" image_size="original" width="100"]
[ux_image id="243357" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'CCELL Flexcell X AIO disposable with EVOMAX ceramic core. Clog-free, all oil types, 280mAh USB-C battery.',
        'categories' => array (
  0 => 'aio-evo-max',
  1 => 'ccell',
  2 => 'disposable',
),
        'thumbnail' => '2026/03/ccell-flexcell-x-aio.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-flexcellx-gallery-01.jpg',
  1 => '2026/03/ccell-flexcellx-gallery-02.jpg',
  2 => '2026/03/ccell-flexcellx-gallery-03.jpg',
  3 => '2026/03/ccell-flexcellx-gallery-04.jpg',
  4 => '2026/03/ccell-flexcellx-gallery-05.jpg',
  5 => '2026/03/ccell-flexcellx-gallery-06.jpg',
  6 => '2026/03/ccell-flexcellx-gallery-07.jpg',
  7 => '2026/03/ccell-flexcellx-gallery-08.jpg',
  8 => '2026/03/ccell-flexcellx-gallery-charging.jpg',
),
        'meta' => array(
            '_sku' => 'FLEXCELLX-AIO-EM',
            '_regular_price' => '9.99',
            '_price' => '9.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '5.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '5.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$9.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$8.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$7.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$6.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$5.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$6.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$5.99',
            'wholesale_customer_wholesale_price' => '6.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '6.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '5.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Flexcell X all-in-one disposable with EVOMAX ceramic. Clog-free, all oils. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-kap-510-power-supply',
        'title' => 'CCELL Kap — 510 Thread Power Supply | Variable Voltage',
        'content' => 'The CCELL Kap is a compact 510-thread power supply with a 500mAh battery, three variable voltage settings, and a magnetic cartridge sleeve for secure, flush-fitting cartridges up to 14mm diameter.

<strong>Key Features:</strong>
<ul>
<li>510 thread connection — compatible with all standard cartridges</li>
<li>500mAh rechargeable battery</li>
<li>Variable voltage: 2.6V / 3.0V / 3.4V</li>
<li>15-second preheat function</li>
<li>Magnetic cartridge sleeve</li>
<li>Supports cartridges up to 14mm diameter</li>
<li>USB-C charging</li>
<li>Inhale-activated</li>
<li>Digital LED display</li>
<li>Compact, discreet design</li>
<li>Dimensions: 90.2 x 40.5 x 13.5 mm</li>
</ul>',
        'excerpt' => 'CCELL Kap 510 power supply. 500mAh, 3 voltage settings (2.6V/3.0V/3.4V), magnetic sleeve, 15s preheat, USB-C.',
        'categories' => array (
  0 => 'ccell',
  1 => 'batteries',
),
        'thumbnail' => '2026/03/ccell-kap-510-power-supply.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-kap-gallery-01.jpg',
  1 => '2026/03/ccell-kap-gallery-02.jpg',
  2 => '2026/03/ccell-kap-gallery-03.jpg',
  3 => '2026/03/ccell-kap-gallery-04.jpg',
  4 => '2026/03/ccell-kap-gallery-05.jpg',
  5 => '2026/03/ccell-kap-gallery-06.jpg',
  6 => '2026/03/ccell-kap-gallery-07.jpg',
  7 => '2026/03/ccell-kap-gallery-08.jpg',
  8 => '2026/03/ccell-kap-gallery-09.jpg',
  9 => '2026/03/ccell-kap-gallery-10.jpg',
  10 => '2026/03/ccell-kap-gallery-11.jpg',
  11 => '2026/03/ccell-kap-gallery-12.jpg',
),
        'meta' => array(
            '_sku' => 'KAP-510-PS',
            '_regular_price' => '14.99',
            '_price' => '14.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '8.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '8.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$14.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$12.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$10.99',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$9.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$8.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$9.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$8.99',
            'wholesale_customer_wholesale_price' => '9.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '9.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '8.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Kap 510 power supply with variable voltage and magnetic cartridge sleeve. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-luster-pro-pod-system',
        'title' => 'CCELL Luster Pro — Variable Wattage Pod System | SE Platform',
        'content' => 'The CCELL Luster Pro is a variable wattage pod system featuring the SE Atomizer Platform, adjustable power settings, and a child-resistant button. Full metal construction with a 350mAh battery delivers premium build quality.

Three wattage levels (2.8V, 3.2V, 3.6V) let users choose between true-to-plant flavor and dense cloud production. A 10-second preheat function ensures clog-free performance.

<strong>Key Features:</strong>
<ul>
<li>0.5ml or 1.0ml magnetic drop-in pod cartridge</li>
<li>CCELL SE Atomizer Platform</li>
<li>350mAh rechargeable battery</li>
<li>USB-C charging</li>
<li>Inhale-activated</li>
<li>Variable wattage: 2.8V / 3.2V / 3.6V</li>
<li>10-second preheat function</li>
<li>Child-resistant button</li>
<li>Full metal construction</li>
<li>Magnetic pod connection</li>
<li>Tamper-proof design</li>
<li>LED display</li>
</ul>',
        'excerpt' => 'CCELL Luster Pro variable wattage pod system with SE heating. 3 voltage settings, preheat, child-resistant, full metal.',
        'categories' => array (
  0 => 'ccell',
  1 => 'pod-systems',
),
        'thumbnail' => '2026/03/ccell-luster-pro-pod.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-lusterpro-gallery-02.jpg',
  1 => '2026/03/ccell-lusterpro-gallery-03.jpg',
),
        'meta' => array(
            '_sku' => 'LUSTERPRO-POD-SE',
            '_regular_price' => '12.99',
            '_price' => '12.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '7.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '7.49',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$12.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$10.99',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$9.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$8.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$7.49',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$8.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$7.49',
            'wholesale_customer_wholesale_price' => '8.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '8.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '7.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Luster Pro variable wattage pod system with SE heating. 350mAh, USB-C, child-resistant. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-m4-tiny-510-battery',
        'title' => 'CCELL M4 Tiny — 510 Thread Battery',
        'content' => 'The CCELL M4 Tiny is an ultra-compact 510-thread battery — one of the smallest CCELL power supplies available. Perfect for discreet use with any standard 510 cartridge.

<strong>Key Features:</strong>
<ul>
<li>510 thread connection</li>
<li>Ultra-compact, ultra-lightweight design</li>
<li>Draw-activated or button-activated (varies by model)</li>
<li>Built-in rechargeable battery</li>
<li>USB-C charging</li>
<li>LED battery indicator</li>
</ul>',
        'excerpt' => 'CCELL M4 Tiny ultra-compact 510-thread battery. One of the smallest CCELL power supplies.',
        'categories' => array (
  0 => 'ccell',
  1 => 'batteries',
),
        'thumbnail' => '2026/03/ccell-m4-tiny-battery.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-m4-tiny-gallery-01.jpg',
  1 => '2026/03/ccell-m4-tiny-gallery-02.jpg',
  2 => '2026/03/ccell-m4-tiny-gallery-03.jpg',
  3 => '2026/03/ccell-m4-tiny-gallery-05.jpg',
  4 => '2026/03/ccell-m4-tiny-gallery-06.jpg',
  5 => '2026/03/ccell-m4-tiny-gallery-07.jpg',
  6 => '2026/03/ccell-m4-tiny-gallery-08.jpg',
),
        'meta' => array(
            '_sku' => 'M4TINY-510',
            '_regular_price' => '14.99',
            '_price' => '14.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '8.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '8.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$14.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$12.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$10.99',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$9.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$8.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$9.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$8.99',
            'wholesale_customer_wholesale_price' => '9.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '9.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '8.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL M4 Tiny ultra-compact 510 battery. Rechargeable, LED indicator. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-m4b-pro-510-battery',
        'title' => 'CCELL M4B Pro — 510 Thread Battery | Variable Voltage',
        'content' => 'The CCELL M4B Pro is a professional-grade 510-thread battery with variable voltage settings. Multiple voltage modes let users dial in the perfect temperature for any oil type and viscosity.

<strong>Key Features:</strong>
<ul>
<li>510 thread connection</li>
<li>Variable voltage settings (multiple modes)</li>
<li>Button-activated with preheat function</li>
<li>Higher-capacity rechargeable battery</li>
<li>USB-C charging</li>
<li>LED voltage indicator</li>
<li>Professional-grade build quality</li>
</ul>',
        'excerpt' => 'CCELL M4B Pro 510-thread battery with variable voltage. Professional-grade power supply for any cartridge.',
        'categories' => array (
  0 => 'ccell',
  1 => 'batteries',
),
        'thumbnail' => '2026/03/ccell-m4b-pro-battery.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-m4b-pro-gallery-01.jpg',
  1 => '2026/03/ccell-m4b-pro-gallery-02.jpg',
  2 => '2026/03/ccell-m4b-pro-gallery-03.jpg',
  3 => '2026/03/ccell-m4b-pro-gallery-04.jpg',
  4 => '2026/03/ccell-m4b-pro-gallery-05.jpg',
  5 => '2026/03/ccell-m4b-pro-gallery-06.jpg',
  6 => '2026/03/ccell-m4b-pro-gallery-07.jpg',
  7 => '2026/03/ccell-m4b-pro-gallery-08.jpg',
  8 => '2026/03/ccell-m4b-pro-gallery-09.jpg',
  9 => '2026/03/ccell-m4b-pro-gallery-10.jpg',
  10 => '2026/03/ccell-m4b-pro-gallery-11.jpg',
  11 => '2026/03/ccell-m4b-pro-gallery-13.jpg',
  12 => '2026/03/ccell-m4b-pro-gallery-14.jpg',
),
        'meta' => array(
            '_sku' => 'M4BPRO-510',
            '_regular_price' => '14.99',
            '_price' => '14.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '8.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '8.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$14.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$12.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$10.99',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$9.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$8.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$9.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$8.99',
            'wholesale_customer_wholesale_price' => '9.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '9.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '8.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL M4B Pro 510 battery with variable voltage. Preheat, USB-C, professional grade. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-m6t05-evo-max-05ml-black-plastic',
        'title' => 'CCELL M6T05 EVO MAX — 0.5ml Poly Cartridge | Black Plastic Mouthpiece',
        'content' => 'The CCELL M6T05 EVO MAX is a 0.5ml ETP (Engineering ThermoPlastic) cartridge featuring the advanced EVO MAX oversized ceramic heating element. The durable BPA-free ETP tank combined with the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance across the full viscosity spectrum.

The Black plastic mouthpiece provides a lightweight finish. Ideal for brands that want EVO MAX performance in a shatter-resistant poly body.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity BPA-free ETP tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Black plastic mouthpiece (press-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Shatter-resistant body</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 0.5ml</li>
<li>Body: BPA-free ETP (Engineering ThermoPlastic)</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: Black plastic mouthpiece (press-on)</li>
<li>Dimensions: 52H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL M6T05 EVO MAX 0.5ml ETP poly cartridge with black plastic mouthpiece. EVO MAX heating for all oil types.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-etp-05ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-etp-main.jpg',
  1 => '2026/03/ccell-evomax-etp-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'M6T05-EVOMAX-BPL',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL M6T05 EVO MAX 0.5ml ETP cartridge with black plastic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-m6t05-evo-max-05ml-clear-plastic',
        'title' => 'CCELL M6T05 EVO MAX — 0.5ml Poly Cartridge | Clear Plastic Mouthpiece',
        'content' => 'The CCELL M6T05 EVO MAX is a 0.5ml ETP (Engineering ThermoPlastic) cartridge featuring the advanced EVO MAX oversized ceramic heating element. The durable BPA-free ETP tank combined with the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance across the full viscosity spectrum.

The Clear plastic mouthpiece provides a transparent finish. Ideal for brands that want EVO MAX performance in a shatter-resistant poly body.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity BPA-free ETP tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Clear plastic mouthpiece (press-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Shatter-resistant body</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 0.5ml</li>
<li>Body: BPA-free ETP (Engineering ThermoPlastic)</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: Clear plastic mouthpiece (press-on)</li>
<li>Dimensions: 52H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL M6T05 EVO MAX 0.5ml ETP poly cartridge with clear plastic mouthpiece. EVO MAX heating for all oil types.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-etp-05ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-etp-main.jpg',
  1 => '2026/03/ccell-evomax-etp-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'M6T05-EVOMAX-CPL',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL M6T05 EVO MAX 0.5ml ETP cartridge with clear plastic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-m6t05-evo-max-05ml-white-plastic',
        'title' => 'CCELL M6T05 EVO MAX — 0.5ml Poly Cartridge | White Plastic Mouthpiece',
        'content' => 'The CCELL M6T05 EVO MAX is a 0.5ml ETP (Engineering ThermoPlastic) cartridge featuring the advanced EVO MAX oversized ceramic heating element. The durable BPA-free ETP tank combined with the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance across the full viscosity spectrum.

The White plastic mouthpiece provides a lightweight finish. Ideal for brands that want EVO MAX performance in a shatter-resistant poly body.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity BPA-free ETP tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>White plastic mouthpiece (press-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Shatter-resistant body</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 0.5ml</li>
<li>Body: BPA-free ETP (Engineering ThermoPlastic)</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: White plastic mouthpiece (press-on)</li>
<li>Dimensions: 52H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL M6T05 EVO MAX 0.5ml ETP poly cartridge with white plastic mouthpiece. EVO MAX heating for all oil types.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-etp-05ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-etp-main.jpg',
  1 => '2026/03/ccell-evomax-etp-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'M6T05-EVOMAX-WPL',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL M6T05 EVO MAX 0.5ml ETP cartridge with white plastic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-m6t05-easy-0-5ml-poly-cartridge-snap-fit',
        'title' => 'CCELL M6T05-Easy - 0.5ML Poly Cartridge with Snap-Fit Mouthpiece',
        'content' => 'The CCELL M6T05-Easy is a 0.5ml ETP (Engineering ThermoPlastic) cartridge from the Essential Series featuring the SE Atomizer Platform. The durable BPA-free ETP tank is ideal for high-volume programs prioritizing durability and cost-effectiveness.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity BPA-free ETP tank</li>
<li>CCELL SE ceramic heating element</li>
<li>Stainless steel airway</li>
<li>Snap-fit closure</li>
<li>510 thread connection</li>
<li>1.4&#8486; resistance</li>
<li>Optimized for distillate formulations</li>
<li>Shatter-resistant body</li>
<li>Viscosity range: 10,000 – 700,000 cP</li>
<li>Dimensions: 57.2H x 10.5W x 10.5D mm</li>
</ul>',
        'excerpt' => 'CCELL M6T05-Easy 0.5ml ETP cartridge with SE ceramic heating. Best value half-gram ETP cartridge.',
        'categories' => array (
  0 => 'ccell-easy',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-m6t05-easy-05ml-01.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-m6t05-easy-05ml-02.jpg',
  1 => '2026/03/ccell-m6t05-easy-05ml-03.jpg',
  2 => '2026/03/ccell-m6t05-easy-05ml-04.jpg',
  3 => '2026/03/ccell-m6t05-easy-05ml-05.jpg',
),
        'meta' => array(
            '_sku' => 'M6T05-EASY-SF',
            '_regular_price' => '3.49',
            '_price' => '3.49',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.69',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.69',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$3.49',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.89',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.39',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.09',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.69',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.09',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$1.69',
            'wholesale_customer_wholesale_price' => '2.09',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.09',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.69',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL M6T05-Easy 0.5ml ETP cartridge with SE ceramic. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-m6t10-evo-max-1ml-black-plastic',
        'title' => 'CCELL M6T10 EVO MAX — 1.0ml Poly Cartridge | Black Plastic Mouthpiece',
        'content' => 'The CCELL M6T10 EVO MAX is a 1.0ml ETP (Engineering ThermoPlastic) cartridge featuring the advanced EVO MAX oversized ceramic heating element. The durable BPA-free ETP tank combined with the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance across the full viscosity spectrum.

The Black plastic mouthpiece provides a lightweight finish. Ideal for brands that want EVO MAX performance in a shatter-resistant poly body.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity BPA-free ETP tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Black plastic mouthpiece (press-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Shatter-resistant body</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: BPA-free ETP (Engineering ThermoPlastic)</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: Black plastic mouthpiece (press-on)</li>
<li>Dimensions: 62H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL M6T10 EVO MAX 1.0ml ETP poly cartridge with black plastic mouthpiece. EVO MAX heating for all oil types.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-etp-10ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-etp-main.jpg',
  1 => '2026/03/ccell-evomax-etp-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'M6T10-EVOMAX-BPL',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL M6T10 EVO MAX 1.0ml ETP cartridge with black plastic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-m6t10-evo-max-1ml-clear-plastic',
        'title' => 'CCELL M6T10 EVO MAX — 1.0ml Poly Cartridge | Clear Plastic Mouthpiece',
        'content' => 'The CCELL M6T10 EVO MAX is a 1.0ml ETP (Engineering ThermoPlastic) cartridge featuring the advanced EVO MAX oversized ceramic heating element. The durable BPA-free ETP tank combined with the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance across the full viscosity spectrum.

The Clear plastic mouthpiece provides a transparent finish. Ideal for brands that want EVO MAX performance in a shatter-resistant poly body.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity BPA-free ETP tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Clear plastic mouthpiece (press-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Shatter-resistant body</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: BPA-free ETP (Engineering ThermoPlastic)</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: Clear plastic mouthpiece (press-on)</li>
<li>Dimensions: 62H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL M6T10 EVO MAX 1.0ml ETP poly cartridge with clear plastic mouthpiece. EVO MAX heating for all oil types.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-etp-10ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-etp-main.jpg',
  1 => '2026/03/ccell-evomax-etp-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'M6T10-EVOMAX-CPL',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL M6T10 EVO MAX 1.0ml ETP cartridge with clear plastic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-m6t10-evo-max-1ml-white-plastic',
        'title' => 'CCELL M6T10 EVO MAX — 1.0ml Poly Cartridge | White Plastic Mouthpiece',
        'content' => 'The CCELL M6T10 EVO MAX is a 1.0ml ETP (Engineering ThermoPlastic) cartridge featuring the advanced EVO MAX oversized ceramic heating element. The durable BPA-free ETP tank combined with the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance across the full viscosity spectrum.

The White plastic mouthpiece provides a lightweight finish. Ideal for brands that want EVO MAX performance in a shatter-resistant poly body.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity BPA-free ETP tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>White plastic mouthpiece (press-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Shatter-resistant body</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: BPA-free ETP (Engineering ThermoPlastic)</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: White plastic mouthpiece (press-on)</li>
<li>Dimensions: 62H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL M6T10 EVO MAX 1.0ml ETP poly cartridge with white plastic mouthpiece. EVO MAX heating for all oil types.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-etp-10ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-etp-main.jpg',
  1 => '2026/03/ccell-evomax-etp-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'M6T10-EVOMAX-WPL',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL M6T10 EVO MAX 1.0ml ETP cartridge with white plastic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-m6t10-easy-1-0ml-poly-cartridge-snap-fit',
        'title' => 'CCELL M6T10-Easy - 1.0ML Poly Cartridge with Snap-Fit Mouthpiece',
        'content' => 'The CCELL M6T10-Easy is a 1.0ml ETP (Engineering ThermoPlastic) cartridge from the Essential Series featuring the SE Atomizer Platform. The durable BPA-free ETP tank is ideal for high-volume programs prioritizing durability and cost-effectiveness.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity BPA-free ETP tank</li>
<li>CCELL SE ceramic heating element</li>
<li>Stainless steel airway</li>
<li>Snap-fit closure</li>
<li>510 thread connection</li>
<li>1.4&#8486; resistance</li>
<li>Optimized for distillate formulations</li>
<li>Shatter-resistant body</li>
<li>Viscosity range: 10,000 – 700,000 cP</li>
</ul>',
        'excerpt' => 'CCELL M6T10-Easy 1.0ml ETP cartridge with SE ceramic heating. Best value full-gram ETP cartridge.',
        'categories' => array (
  0 => 'ccell-easy',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-m6t10-easy-1ml-hero.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-m6t10-easy-1ml-gallery-01.jpg',
  1 => '2026/03/ccell-m6t10-easy-1ml-gallery-02.jpg',
  2 => '2026/03/ccell-m6t10-easy-1ml-gallery-03.jpg',
  3 => '2026/03/ccell-m6t10-easy-1ml-gallery-04.jpg',
  4 => '2026/03/ccell-m6t10-easy-1ml-gallery-05.jpg',
),
        'meta' => array(
            '_sku' => 'M6T10-EASY-SF',
            '_regular_price' => '3.99',
            '_price' => '3.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.89',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.89',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$3.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$3.27',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.61',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.09',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.89',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.09',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$1.89',
            'wholesale_customer_wholesale_price' => '2.09',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.09',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.89',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL M6T10-Easy 1.0ml ETP cartridge with SE ceramic. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-mini-tank-2-0-2ml-aio',
        'title' => 'CCELL Mini Tank 2.0 — 2.0ml All-In-One',
        'content' => 'The CCELL Mini Tank 2.0 is the high-capacity version of the Mini Tank platform, packing 2.0ml into the same ultra-compact form factor. Built on the advanced EVO MAX ceramic heating platform for superior vapor quality and flavor across all oil types — from distillates to live rosins.

The 200mAh USB-C rechargeable battery ensures the device lasts through the full 2.0ml capacity. Aroma Seal technology with an optional anti-leak switch and clog-free dual air vents deliver a reliable, leak-free experience.

<strong>Key Features:</strong>
<ul>
<li>2.0ml capacity — double the standard</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>200mAh rechargeable battery (USB-C)</li>
<li>Aroma Seal with optional anti-leak switch</li>
<li>Clog-free dual air vents</li>
<li>Inhale-activated — no buttons</li>
<li>Ultra-compact plastic body</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 2.0ml</li>
<li>Heating: EVO MAX oversized ceramic</li>
<li>Battery: 200mAh (USB-C)</li>
<li>Body: Plastic</li>
<li>Activation: Inhale-activated</li>
<li>Dimensions: 63H x 36W x 15D mm</li>
<li>Color: Black</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243327" image_size="original" width="100"]
[ux_image id="243328" image_size="original" width="100"]
[ux_image id="243329" image_size="original" width="100"]
[ux_image id="243330" image_size="original" width="100"]
[ux_image id="243331" image_size="original" width="100"]
[ux_image id="243332" image_size="original" width="100"]
[ux_image id="243333" image_size="original" width="100"]
[ux_image id="243334" image_size="original" width="100"]
[ux_image id="243335" image_size="original" width="100"]
[ux_image id="243336" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'CCELL Mini Tank 2.0 with 2.0ml capacity, EVO MAX ceramic heating, 200mAh USB-C battery, Aroma Seal, and clog-free dual vents. Ultra-compact at 63mm.',
        'categories' => array (
  0 => 'aio-evo-max',
  1 => 'ccell',
  2 => 'disposable',
),
        'thumbnail' => '2026/03/ccell-mini-tank-2ml-hero.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-mini-tank-gallery-01.jpg',
  1 => '2026/03/ccell-mini-tank-gallery-02.jpg',
  2 => '2026/03/ccell-mini-tank-gallery-03.jpg',
  3 => '2026/03/ccell-mini-tank-gallery-04.jpg',
  4 => '2026/03/ccell-mini-tank-gallery-05.jpg',
  5 => '2026/03/ccell-mini-tank-gallery-07.jpg',
  6 => '2026/03/ccell-mini-tank-gallery-08.jpg',
  7 => '2026/03/ccell-mini-tank-gallery-09.jpg',
  8 => '2026/03/ccell-mini-tank-gallery-size.jpg',
),
        'meta' => array(
            '_sku' => 'MINITANK-20-20',
            '_regular_price' => '9.99',
            '_price' => '9.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '5.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '5.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$9.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$8.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$7.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$6.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$5.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$6.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$5.99',
            'wholesale_customer_wholesale_price' => '6.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '6.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '5.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Mini Tank 2.0 2.0ml all-in-one with EVO MAX ceramic heating and 200mAh USB-C battery. Double capacity, ultra-compact. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-mini-tank-em-1-0ml-aio',
        'title' => 'CCELL Mini Tank EM — 1.0ml All-In-One | EVO MAX',
        'content' => 'The CCELL Mini Tank EM is the EVOMAX-powered version of the Mini Tank platform. The oversized EVOMAX ceramic heating element with thicker walls delivers superior heat distribution and enhanced vapor production across all oil types — from distillates to live rosins and liquid diamonds.

Same ultra-compact form factor as the Mini Tank SE with a 200mAh USB-C rechargeable battery, Aroma Seal technology, and clog-free dual air vents. The EVOMAX upgrade brings premium coil performance to the most portable AIO in the lineup.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity</li>
<li>EVOMAX oversized ceramic heating element</li>
<li>200mAh rechargeable battery (USB-C)</li>
<li>Aroma Seal with optional anti-leak switch</li>
<li>Clog-free dual air vents</li>
<li>Inhale-activated — no buttons</li>
<li>Ultra-compact plastic body</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Heating: EVOMAX oversized ceramic element</li>
<li>Battery: 200mAh (USB-C)</li>
<li>Body: Plastic</li>
<li>Activation: Inhale-activated</li>
<li>Dimensions: 63H x 36W x 15D mm</li>
<li>Color: Black</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243327" image_size="original" width="100"]
[ux_image id="243328" image_size="original" width="100"]
[ux_image id="243329" image_size="original" width="100"]
[ux_image id="243330" image_size="original" width="100"]
[ux_image id="243331" image_size="original" width="100"]
[ux_image id="243332" image_size="original" width="100"]
[ux_image id="243333" image_size="original" width="100"]
[ux_image id="243334" image_size="original" width="100"]
[ux_image id="243335" image_size="original" width="100"]
[ux_image id="243336" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'CCELL Mini Tank EM 1.0ml AIO with EVOMAX ceramic heating, 200mAh USB-C battery, Aroma Seal, and clog-free dual vents. All oil types.',
        'categories' => array (
  0 => 'aio-evo-max',
  1 => 'ccell',
  2 => 'disposable',
),
        'thumbnail' => '2026/03/ccell-mini-tank-em-1ml-hero.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-mini-tank-gallery-01.jpg',
  1 => '2026/03/ccell-mini-tank-gallery-02.jpg',
  2 => '2026/03/ccell-mini-tank-gallery-03.jpg',
  3 => '2026/03/ccell-mini-tank-gallery-04.jpg',
  4 => '2026/03/ccell-mini-tank-gallery-05.jpg',
  5 => '2026/03/ccell-mini-tank-gallery-07.jpg',
  6 => '2026/03/ccell-mini-tank-gallery-08.jpg',
  7 => '2026/03/ccell-mini-tank-gallery-09.jpg',
  8 => '2026/03/ccell-mini-tank-gallery-size.jpg',
),
        'meta' => array(
            '_sku' => 'MINITANK-EM-10',
            '_regular_price' => '9.99',
            '_price' => '9.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '5.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '5.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$9.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$8.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$7.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$6.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$5.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$6.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$5.99',
            'wholesale_customer_wholesale_price' => '6.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '6.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '5.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Mini Tank EM 1.0ml all-in-one with EVOMAX ceramic heating and 200mAh USB-C battery. All oil types. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-mini-tank-se-1-0ml-aio',
        'title' => 'CCELL Mini Tank SE — 1.0ml All-In-One',
        'content' => 'The CCELL Mini Tank SE is an ultra-compact 1.0ml all-in-one disposable built on the SE ceramic heating platform — the original CCELL coil technology trusted across the industry for smooth, reliable distillate vaporization.

The Mini Tank\'s pocket-friendly form factor packs a 200mAh USB-C rechargeable battery, Aroma Seal technology with an optional anti-leak switch, and clog-free dual air vents into a device just 63mm tall. Inhale-activated for effortless use.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity</li>
<li>SE ceramic heating element</li>
<li>200mAh rechargeable battery (USB-C)</li>
<li>Aroma Seal with optional anti-leak switch</li>
<li>Clog-free dual air vents</li>
<li>Inhale-activated — no buttons</li>
<li>Ultra-compact plastic body</li>
<li>Optimized for distillate formulations</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Heating: SE ceramic element</li>
<li>Battery: 200mAh (USB-C)</li>
<li>Body: Plastic</li>
<li>Activation: Inhale-activated</li>
<li>Dimensions: 63H x 36W x 15D mm</li>
<li>Color: Black</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243327" image_size="original" width="100"]
[ux_image id="243328" image_size="original" width="100"]
[ux_image id="243329" image_size="original" width="100"]
[ux_image id="243330" image_size="original" width="100"]
[ux_image id="243331" image_size="original" width="100"]
[ux_image id="243332" image_size="original" width="100"]
[ux_image id="243333" image_size="original" width="100"]
[ux_image id="243334" image_size="original" width="100"]
[ux_image id="243335" image_size="original" width="100"]
[ux_image id="243336" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'CCELL Mini Tank SE 1.0ml AIO with SE ceramic heating, 200mAh USB-C battery, Aroma Seal, and clog-free dual vents. Ultra-compact at 63mm.',
        'categories' => array (
  0 => 'aio-se-standard',
  1 => 'ccell',
  2 => 'disposable',
),
        'thumbnail' => '2026/03/ccell-mini-tank-se-1ml-hero.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-mini-tank-gallery-01.jpg',
  1 => '2026/03/ccell-mini-tank-gallery-02.jpg',
  2 => '2026/03/ccell-mini-tank-gallery-03.jpg',
  3 => '2026/03/ccell-mini-tank-gallery-04.jpg',
  4 => '2026/03/ccell-mini-tank-gallery-05.jpg',
  5 => '2026/03/ccell-mini-tank-gallery-07.jpg',
  6 => '2026/03/ccell-mini-tank-gallery-08.jpg',
  7 => '2026/03/ccell-mini-tank-gallery-09.jpg',
  8 => '2026/03/ccell-mini-tank-gallery-size.jpg',
),
        'meta' => array(
            '_sku' => 'MINITANK-SE-10',
            '_regular_price' => '9.99',
            '_price' => '9.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '5.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '5.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$9.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$8.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$7.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$6.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$5.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$6.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$5.99',
            'wholesale_customer_wholesale_price' => '6.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '6.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '5.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Mini Tank SE 1.0ml all-in-one with SE ceramic heating and 200mAh USB-C battery. Ultra-compact disposable. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-palm-se-510-battery',
        'title' => 'CCELL Palm SE — 510 Thread Battery',
        'content' => 'The CCELL Palm SE is a compact 510-thread battery designed for use with any standard 510 cartridge. The palm-sized form factor features inhale-activated firing and a built-in rechargeable battery for convenient, buttonless operation.

<strong>Key Features:</strong>
<ul>
<li>510 thread connection — compatible with all standard cartridges</li>
<li>Draw-activated firing (no button)</li>
<li>Built-in rechargeable lithium-ion battery</li>
<li>USB-C charging</li>
<li>Compact palm-sized form factor</li>
<li>LED battery indicator</li>
</ul>',
        'excerpt' => 'CCELL Palm SE 510-thread battery. Compact, draw-activated, USB-C rechargeable.',
        'categories' => array (
  0 => 'ccell',
  1 => 'batteries',
),
        'thumbnail' => '2026/03/ccell-palm-se-battery.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-palm-se-gallery-01.jpg',
  1 => '2026/03/ccell-palm-se-gallery-02.jpg',
  2 => '2026/03/ccell-palm-se-gallery-03.jpg',
  3 => '2026/03/ccell-palm-se-gallery-04.jpg',
  4 => '2026/03/ccell-palm-se-gallery-05.jpg',
),
        'meta' => array(
            '_sku' => 'PALMSE-510',
            '_regular_price' => '14.99',
            '_price' => '14.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '8.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '8.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$14.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$12.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$10.99',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$9.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$8.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$9.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$8.99',
            'wholesale_customer_wholesale_price' => '9.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '9.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '8.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Palm SE 510-thread battery. Compact, draw-activated, USB-C charging. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-rosin-bar-1-0ml-aio',
        'title' => 'CCELL Rosin Bar — 1.0ml All-In-One | HeRo Platform',
        'content' => 'The CCELL Rosin Bar 1.0ml is the full-gram version of the Rosin Bar, purpose-built for live rosin formulations using the HeRo heating platform. The HeRo system features partitioned atomization — THC and terpenes are heated at optimal temperatures in separate zones via multi-level heating distribution, preserving authentic strain flavors while maximizing potency.

The 1.0ml variant uses an ETP body (vs. metal on the 0.5ml) and a 200mAh USB-C rechargeable battery. Dual oil pathways within the heating element ensure continuous oil supply, eliminating dry hits and clogs. An isolated airway keeps vapor clean and free from contact with battery and electronic components.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity</li>
<li>HeRo heating platform — partitioned THC + terpene atomization</li>
<li>Dual oil pathways for clog-free operation</li>
<li>200mAh rechargeable battery (USB-C)</li>
<li>Isolated airway — pure, clean vapor</li>
<li>Dual air vents</li>
<li>Inhale-activated</li>
<li>LED indicator light</li>
<li>ETP body construction</li>
<li>Purpose-built for live rosin formulations</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Heating: HeRo platform (partitioned atomization)</li>
<li>Battery: 200mAh (USB-C)</li>
<li>Body: ETP</li>
<li>Activation: Inhale-activated</li>
<li>Dimensions: 90H x 24W x 13D mm</li>
<li>Indicator: LED</li>
</ul>',
        'excerpt' => 'CCELL Rosin Bar 1.0ml AIO with HeRo partitioned atomization for live rosin. Dual oil pathways, 200mAh USB-C, isolated airway. ETP body.',
        'categories' => array (
  0 => 'aio-hero',
  1 => 'ccell',
  2 => 'disposable',
),
        'thumbnail' => '2026/03/ccell-rosin-bar-1ml-hero.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-rosin-bar-spin-01.jpg',
  1 => '2026/03/ccell-rosin-bar-spin-02.jpg',
  2 => '2026/03/ccell-rosin-bar-spin-03.jpg',
  3 => '2026/03/ccell-rosin-bar-spin-04.jpg',
  4 => '2026/03/ccell-rosin-bar-spin-05.jpg',
  5 => '2026/03/ccell-rosin-bar-spin-06.jpg',
  6 => '2026/03/ccell-rosin-bar-spin-07.jpg',
  7 => '2026/03/ccell-rosin-bar-spin-08.jpg',
  8 => '2026/03/ccell-rosin-bar-spin-09.jpg',
  9 => '2026/03/ccell-rosin-bar-spin-size.jpg',
),
        'meta' => array(
            '_sku' => 'ROSINBAR-10-HR',
            '_regular_price' => '9.99',
            '_price' => '9.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '5.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '5.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$9.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$8.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$7.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$6.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$5.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$6.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$5.99',
            'wholesale_customer_wholesale_price' => '6.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '6.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '5.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Rosin Bar 1.0ml all-in-one with HeRo partitioned atomization for live rosin. Dual oil pathways, clog-free. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th205-evo-max-05ml-black-ceramic',
        'title' => 'CCELL TH205 EVO MAX — 0.5ml Glass Cartridge | Black Ceramic Mouthpiece',
        'content' => 'The CCELL TH205 EVO MAX is a 0.5ml glass-body cartridge featuring the advanced EVO MAX oversized ceramic heating element. Engineered to handle the full viscosity spectrum — from thin distillates to thick live rosins and liquid diamonds — the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance from the very first puff.

The Black ceramic mouthpiece provides a clean, premium finish. Combined with the borosilicate glass tank for full oil visibility and a standard 510 thread connection, this cartridge is built for brands that demand top-tier performance across every formulation.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity borosilicate glass tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Black ceramic mouthpiece (screw-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Leak-proof design with 4 × 1.6mm intake holes</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 0.5ml</li>
<li>Body: Borosilicate glass</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: Black ceramic mouthpiece (screw-on)</li>
<li>Dimensions: 51.8H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with black ceramic mouthpiece. Handles distillate through live rosin.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-glass-05ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-glass-main.jpg',
  1 => '2026/03/ccell-evomax-glass-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'TH205-EVOMAX-BC',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with black ceramic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th205-evo-max-05ml-black-plastic',
        'title' => 'CCELL TH205 EVO MAX — 0.5ml Glass Cartridge | Black Plastic Mouthpiece',
        'content' => 'The CCELL TH205 EVO MAX is a 0.5ml glass-body cartridge featuring the advanced EVO MAX oversized ceramic heating element. Engineered to handle the full viscosity spectrum — from thin distillates to thick live rosins and liquid diamonds — the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance from the very first puff.

The Black plastic mouthpiece provides a lightweight finish. Combined with the borosilicate glass tank for full oil visibility and a standard 510 thread connection, this cartridge is built for brands that demand top-tier performance across every formulation.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity borosilicate glass tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Black plastic mouthpiece (screw-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Leak-proof design with 4 × 1.6mm intake holes</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 0.5ml</li>
<li>Body: Borosilicate glass</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: Black plastic mouthpiece (screw-on)</li>
<li>Dimensions: 51.8H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with black plastic mouthpiece. Handles distillate through live rosin.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-glass-05ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-glass-main.jpg',
  1 => '2026/03/ccell-evomax-glass-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'TH205-EVOMAX-BPL',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with black plastic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th205-evo-max-05ml-clear-plastic',
        'title' => 'CCELL TH205 EVO MAX — 0.5ml Glass Cartridge | Clear Plastic Mouthpiece',
        'content' => 'The CCELL TH205 EVO MAX is a 0.5ml glass-body cartridge featuring the advanced EVO MAX oversized ceramic heating element. Engineered to handle the full viscosity spectrum — from thin distillates to thick live rosins and liquid diamonds — the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance from the very first puff.

The Clear plastic mouthpiece provides a transparent finish. Combined with the borosilicate glass tank for full oil visibility and a standard 510 thread connection, this cartridge is built for brands that demand top-tier performance across every formulation.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity borosilicate glass tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Clear plastic mouthpiece (screw-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Leak-proof design with 4 × 1.6mm intake holes</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 0.5ml</li>
<li>Body: Borosilicate glass</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: Clear plastic mouthpiece (screw-on)</li>
<li>Dimensions: 51.8H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with clear plastic mouthpiece. Handles distillate through live rosin.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-glass-05ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-glass-main.jpg',
  1 => '2026/03/ccell-evomax-glass-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'TH205-EVOMAX-CPL',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with clear plastic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th205-evo-max-05ml-white-ceramic',
        'title' => 'CCELL TH205 EVO MAX — 0.5ml Glass Cartridge | White Ceramic Mouthpiece',
        'content' => 'The CCELL TH205 EVO MAX is a 0.5ml glass-body cartridge featuring the advanced EVO MAX oversized ceramic heating element. Engineered to handle the full viscosity spectrum — from thin distillates to thick live rosins and liquid diamonds — the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance from the very first puff.

The White ceramic mouthpiece provides a clean, premium finish. Combined with the borosilicate glass tank for full oil visibility and a standard 510 thread connection, this cartridge is built for brands that demand top-tier performance across every formulation.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity borosilicate glass tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>White ceramic mouthpiece (screw-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Leak-proof design with 4 × 1.6mm intake holes</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 0.5ml</li>
<li>Body: Borosilicate glass</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: White ceramic mouthpiece (screw-on)</li>
<li>Dimensions: 51.8H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with white ceramic mouthpiece. Handles distillate through live rosin.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-glass-05ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-glass-main.jpg',
  1 => '2026/03/ccell-evomax-glass-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'TH205-EVOMAX-WC',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with white ceramic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th205-evo-max-05ml-white-plastic',
        'title' => 'CCELL TH205 EVO MAX — 0.5ml Glass Cartridge | White Plastic Mouthpiece',
        'content' => 'The CCELL TH205 EVO MAX is a 0.5ml glass-body cartridge featuring the advanced EVO MAX oversized ceramic heating element. Engineered to handle the full viscosity spectrum — from thin distillates to thick live rosins and liquid diamonds — the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance from the very first puff.

The White plastic mouthpiece provides a lightweight finish. Combined with the borosilicate glass tank for full oil visibility and a standard 510 thread connection, this cartridge is built for brands that demand top-tier performance across every formulation.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity borosilicate glass tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>White plastic mouthpiece (screw-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Leak-proof design with 4 × 1.6mm intake holes</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 0.5ml</li>
<li>Body: Borosilicate glass</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: White plastic mouthpiece (screw-on)</li>
<li>Dimensions: 51.8H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with white plastic mouthpiece. Handles distillate through live rosin.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-glass-05ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-glass-main.jpg',
  1 => '2026/03/ccell-evomax-glass-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'TH205-EVOMAX-WPL',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with white plastic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th205-easy-0-5ml-glass-cartridge-snap-fit',
        'title' => 'CCELL TH205-Easy - 0.5ML Glass Cartridge with Snap-Fit Mouthpiece',
        'content' => 'The CCELL TH205-Easy is a 0.5ml borosilicate glass cartridge from the Essential Series featuring the SE Atomizer Platform. The glass tank provides excellent oil visibility, while the snap-fit mouthpiece enables quick, tool-free assembly. Designed for value without compromising performance — ideal for distillate programs.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity borosilicate glass tank</li>
<li>CCELL SE ceramic heating element</li>
<li>Stainless steel airway</li>
<li>Snap-fit closure</li>
<li>510 thread connection</li>
<li>1.4&#8486; resistance</li>
<li>Optimized for distillate formulations</li>
<li>Leak-free, clog-free design</li>
<li>Viscosity range: 10,000 – 700,000 cP</li>
<li>Dimensions: 51.8H x 10.5W x 10.5D mm</li>
</ul>',
        'excerpt' => 'CCELL TH205-Easy 0.5ml glass cartridge with SE ceramic heating. Best value glass cartridge for distillate programs.',
        'categories' => array (
  0 => 'ccell-easy',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-th205-easy-05ml-gallery-03.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-th205-easy-05ml-gallery-04.jpg',
  1 => '2026/03/ccell-th205-easy-05ml-gallery-06.jpg',
),
        'meta' => array(
            '_sku' => 'TH205-EASY-SF',
            '_regular_price' => '4.09',
            '_price' => '4.09',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.95',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.95',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.09',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$3.35',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.68',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.15',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.95',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.15',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$1.95',
            'wholesale_customer_wholesale_price' => '2.15',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.15',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.95',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH205-Easy 0.5ml glass cartridge with SE ceramic. Snap-fit closure, 510 thread. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-evo-max-1ml-black-ceramic',
        'title' => 'CCELL TH210 EVO MAX — 1.0ml Glass Cartridge | Black Ceramic Mouthpiece',
        'content' => 'The CCELL TH210 EVO MAX is a 1.0ml glass-body cartridge featuring the advanced EVO MAX oversized ceramic heating element. Engineered to handle the full viscosity spectrum — from thin distillates to thick live rosins and liquid diamonds — the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance from the very first puff.

The Black ceramic mouthpiece provides a clean, premium finish. Combined with the borosilicate glass tank for full oil visibility and a standard 510 thread connection, this cartridge is built for brands that demand top-tier performance across every formulation.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity borosilicate glass tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Black ceramic mouthpiece (screw-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Leak-proof design with 4 × 1.6mm intake holes</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: Borosilicate glass</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: Black ceramic mouthpiece (screw-on)</li>
<li>Dimensions: 66.1H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with black ceramic mouthpiece. Handles distillate through live rosin.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-glass-10ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-glass-main.jpg',
  1 => '2026/03/ccell-evomax-glass-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'TH210-EVOMAX-BC',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with black ceramic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-evo-max-1ml-black-plastic',
        'title' => 'CCELL TH210 EVO MAX — 1.0ml Glass Cartridge | Black Plastic Mouthpiece',
        'content' => 'The CCELL TH210 EVO MAX is a 1.0ml glass-body cartridge featuring the advanced EVO MAX oversized ceramic heating element. Engineered to handle the full viscosity spectrum — from thin distillates to thick live rosins and liquid diamonds — the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance from the very first puff.

The Black plastic mouthpiece provides a lightweight finish. Combined with the borosilicate glass tank for full oil visibility and a standard 510 thread connection, this cartridge is built for brands that demand top-tier performance across every formulation.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity borosilicate glass tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Black plastic mouthpiece (screw-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Leak-proof design with 4 × 1.6mm intake holes</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: Borosilicate glass</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: Black plastic mouthpiece (screw-on)</li>
<li>Dimensions: 66.1H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with black plastic mouthpiece. Handles distillate through live rosin.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-glass-10ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-glass-main.jpg',
  1 => '2026/03/ccell-evomax-glass-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'TH210-EVOMAX-BPL',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with black plastic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-evo-max-1ml-clear-plastic',
        'title' => 'CCELL TH210 EVO MAX — 1.0ml Glass Cartridge | Clear Plastic Mouthpiece',
        'content' => 'The CCELL TH210 EVO MAX is a 1.0ml glass-body cartridge featuring the advanced EVO MAX oversized ceramic heating element. Engineered to handle the full viscosity spectrum — from thin distillates to thick live rosins and liquid diamonds — the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance from the very first puff.

The Clear plastic mouthpiece provides a transparent finish. Combined with the borosilicate glass tank for full oil visibility and a standard 510 thread connection, this cartridge is built for brands that demand top-tier performance across every formulation.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity borosilicate glass tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Clear plastic mouthpiece (screw-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Leak-proof design with 4 × 1.6mm intake holes</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: Borosilicate glass</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: Clear plastic mouthpiece (screw-on)</li>
<li>Dimensions: 66.1H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with clear plastic mouthpiece. Handles distillate through live rosin.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-glass-10ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-glass-main.jpg',
  1 => '2026/03/ccell-evomax-glass-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'TH210-EVOMAX-CPL',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with clear plastic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-evo-max-1-0ml-glass-white-ceramic',
        'title' => 'CCELL TH210 EVO MAX — 1.0ml Glass Cartridge | White Ceramic Mouthpiece',
        'content' => 'The CCELL TH210 EVO MAX is a 1.0ml glass-body cartridge featuring the advanced EVO MAX oversized ceramic heating element. Engineered to handle the full viscosity spectrum — from thin distillates to thick live rosins and liquid diamonds — the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance from the very first puff.

The white ceramic mouthpiece provides a clean, premium aesthetic ideal for brands seeking a polished look. Combined with the borosilicate glass tank for full oil visibility and a standard 510 thread connection, this cartridge is built for brands that demand top-tier performance across every formulation.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity borosilicate glass tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>White ceramic screw-on mouthpiece</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Leak-proof design with 4 × 1.6mm intake holes</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: Borosilicate glass</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2Ω</li>
<li>Thread: 510</li>
<li>Mouthpiece: White ceramic (screw-on)</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Viscosity Range: 10,000 – 2,000,000 cP</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with oversized ceramic heating element and white ceramic mouthpiece. Handles every oil type — distillate, live resin, live rosin, and liquid diamonds — with denser vapor and better flavor from the first puff.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2024/09/TH210EVO-WC.png',
        'gallery' => array (
  0 => '2026/02/ccell-evomax-th2-official-3.png',
  1 => '2026/02/ccell-evomax-th2-official-6.png',
  2 => '2026/02/ccell-evomax-th2-lifestyle-2.jpg',
  3 => '2026/02/ccell-evomax-th2-lifestyle-3.jpg',
  4 => '2026/02/ccell-evomax-th2-lifestyle-4.jpg',
  5 => '2026/02/ccell-evomax-th2-official-1.jpg',
),
        'meta' => array(
            '_sku' => 'TH210-EVOMAX-WC',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with white ceramic mouthpiece. Oversized EVO MAX ceramic coil handles distillate through live rosin. Wholesale pricing from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-evo-max-1ml-white-plastic',
        'title' => 'CCELL TH210 EVO MAX — 1.0ml Glass Cartridge | White Plastic Mouthpiece',
        'content' => 'The CCELL TH210 EVO MAX is a 1.0ml glass-body cartridge featuring the advanced EVO MAX oversized ceramic heating element. Engineered to handle the full viscosity spectrum — from thin distillates to thick live rosins and liquid diamonds — the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance from the very first puff.

The White plastic mouthpiece provides a lightweight finish. Combined with the borosilicate glass tank for full oil visibility and a standard 510 thread connection, this cartridge is built for brands that demand top-tier performance across every formulation.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity borosilicate glass tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>White plastic mouthpiece (screw-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Leak-proof design with 4 × 1.6mm intake holes</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: Borosilicate glass</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: White plastic mouthpiece (screw-on)</li>
<li>Dimensions: 66.1H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>',
        'excerpt' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with white plastic mouthpiece. Handles distillate through live rosin.',
        'categories' => array (
  0 => 'ccell-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2026/03/ccell-evomax-glass-10ml.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-evomax-glass-main.jpg',
  1 => '2026/03/ccell-evomax-glass-size.jpg',
  2 => '2026/03/ccell-evomax-atomizer.png',
),
        'meta' => array(
            '_sku' => 'TH210-EVOMAX-WPL',
            '_regular_price' => '4.99',
            '_price' => '4.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.49',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.49',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.19',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.49',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.49',
            'wholesale_customer_wholesale_price' => '2.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.49',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with white plastic mouthpiece. Wholesale from Hamilton Devices.',
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-easy-1-0ml-glass-cartridge-snap-fit',
        'title' => 'CCELL TH210-Easy - 1.0ML Glass Cartridge with Snap-Fit Mouthpiece',
        'content' => 'The CCELL TH210-Easy is a 1.0ml borosilicate glass cartridge from the Essential Series featuring the SE Atomizer Platform. The full-gram glass tank is ideal for brands offering larger products with premium oil visibility. Snap-fit mouthpiece for quick assembly.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity borosilicate glass tank</li>
<li>CCELL SE ceramic heating element</li>
<li>Stainless steel airway</li>
<li>Snap-fit closure</li>
<li>510 thread connection</li>
<li>1.4&#8486; resistance</li>
<li>Optimized for distillate formulations</li>
<li>Leak-free, clog-free design</li>
<li>Viscosity range: 10,000 – 700,000 cP</li>
<li>Dimensions: 62.1H x 10.5W x 10.5D mm</li>
</ul>',
        'excerpt' => 'CCELL TH210-Easy 1.0ml glass cartridge with SE ceramic heating. Best value full-gram glass cartridge for distillate.',
        'categories' => array (
  0 => 'ccell-easy',
  1 => 'ccell',
  2 => 'cartridge',
),
        'thumbnail' => '2024/09/TH210-S-White-Ceramic-Screw.jpg',
        'gallery' => array (
  0 => '2025/03/Flat-TH2-S-1ML.jpg',
  1 => '2025/03/Flute-TH2-S-1ML-1.jpg',
),
        'meta' => array(
            '_sku' => 'TH210-EASY-SF',
            '_regular_price' => '4.19',
            '_price' => '4.19',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.19',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$3.43',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.75',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.20',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.20',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$1.99',
            'wholesale_customer_wholesale_price' => '2.20',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.20',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH210-Easy 1.0ml glass cartridge with SE ceramic. Snap-fit, 510 thread. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-turboom-2-0ml-aio-disposable',
        'title' => 'CCELL TurBoom — 2.0ml Dual-Core All-In-One | EVOMAX',
        'content' => 'The CCELL TurBoom is a high-performance dual-core all-in-one disposable featuring EVOMAX heating technology. Inspired by twin-engine aircraft design, its single-tank dual-core architecture delivers up to 16W of output power — 2-3x more cannabinoid delivery per puff than standard devices (8-10mg per puff).

Three adjustable power modes (Eco, Normal, Boost) let users dial in their experience, while the smart display screen shows power level and battery status.

<strong>Key Features:</strong>
<ul>
<li>2.0ml reservoir capacity</li>
<li>CCELL EVOMAX dual-core heating (up to 16W)</li>
<li>8-10mg cannabinoids per puff</li>
<li>3 power modes: Eco, Normal, Boost</li>
<li>500mAh rechargeable battery</li>
<li>USB-C charging</li>
<li>Inhale-activated</li>
<li>Smart display screen</li>
<li>Preheat function</li>
<li>Customizable observation window</li>
<li>Dual air vents</li>
<li>BPA-free PA material, snap-fit closure</li>
<li>Dimensions: 77.6 x 41.8 x 17.4 mm</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243399" image_size="original" width="100"]
[ux_image id="243400" image_size="original" width="100"]
[ux_image id="243401" image_size="original" width="100"]
[ux_image id="243402" image_size="original" width="100"]
[ux_image id="243403" image_size="original" width="100"]
[ux_image id="243404" image_size="original" width="100"]
[ux_image id="243405" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'CCELL TurBoom 2.0ml dual-core AIO with EVOMAX heating. Up to 16W, 3 power modes, smart display, 500mAh USB-C battery.',
        'categories' => array (
  0 => 'aio-evo-max',
  1 => 'ccell',
  2 => 'disposable',
),
        'thumbnail' => '2026/03/ccell-turboom-aio.jpg',
        'gallery' => array (
  0 => '2026/03/ccell-turboom-gallery-01.jpg',
  1 => '2026/03/ccell-turboom-gallery-02.jpg',
  2 => '2026/03/ccell-turboom-gallery-03.jpg',
  3 => '2026/03/ccell-turboom-gallery-04.jpg',
  4 => '2026/03/ccell-turboom-gallery-05.jpg',
  5 => '2026/03/ccell-turboom-gallery-06.jpg',
  6 => '2026/03/ccell-turboom-gallery-07.jpg',
  7 => '2026/03/ccell-turboom-gallery-08.jpg',
  8 => '2026/03/ccell-turboom-gallery-10.jpg',
  9 => '2026/03/ccell-turboom-gallery-11.jpg',
),
        'meta' => array(
            '_sku' => 'TURBOOM-20-AIO',
            '_regular_price' => '9.99',
            '_price' => '9.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '5.99',
            'wprice_text' => 'As low as',
            'wlowest_price' => '5.99',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$9.99',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$8.49',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$7.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$6.99',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$5.99',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$6.99',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$5.99',
            'wholesale_customer_wholesale_price' => '6.99',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '6.99',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '5.99',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TurBoom 2.0ml dual-core all-in-one disposable with EVOMAX. Up to 16W, 3 power modes. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-gembox',
        'title' => 'CCELL® GemBox',
        'content' => '

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243371" image_size="original" width="100"]
[ux_image id="243372" image_size="original" width="100"]
[ux_image id="243373" image_size="original" width="100"]
[ux_image id="243374" image_size="original" width="100"]
[ux_image id="243375" image_size="original" width="100"]
[ux_image id="243376" image_size="original" width="100"]
[ux_image id="243377" image_size="original" width="100"]
[ux_image id="243378" image_size="original" width="100"]
[ux_image id="243379" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => '<strong>Unobstructed Clarity </strong>

<span data-contrast="auto">Experience the future of disposables with the CCELL GemBox. Featuring a revolutionary Postless Tank Design, this device removes the conventional center post to offer a clean, 360° panoramic view of your oil3. The prism-cut tank enhances oil fluidity and allows the natural beauty of your extract to shine4.</span><span data-ccp-props="{&quot;335559739&quot;:240}"> </span>

<strong>Smart &amp; Seamless </strong>

<span data-contrast="auto">Stay in control with the Seamless Smart Display. The built-in screen provides real-time updates on your battery life and voltage settings with every draw5.</span><span data-ccp-props="{&quot;335559739&quot;:240}"> </span>

<strong>Pure Flavor, No Burn </strong>

<span data-contrast="auto">Powered by VeinMesh Design, the GemBox utilizes an ultra-low heating temperature solution that is 100% cotton-free. This ensures consistent vapor and zero burnt flavors stemming from the core. Additionally, the 3D Stomata Design offers exceptional protection against leaks and clogs.</span><span data-ccp-props="{&quot;335559739&quot;:240}"> </span>

<strong>Specifications: </strong>
<ul>
 	<li aria-setsize="-1" data-leveltext="●" data-font="Arial" data-listid="1" data-list-defn-props="{&quot;335552541&quot;:1,&quot;335559685&quot;:720,&quot;335559991&quot;:360,&quot;469769226&quot;:&quot;Arial&quot;,&quot;469769242&quot;:[8226],&quot;469777803&quot;:&quot;left&quot;,&quot;469777804&quot;:&quot;●&quot;,&quot;469777815&quot;:&quot;multilevel&quot;}" data-aria-posinset="1" data-aria-level="1"><b><span data-contrast="auto">Tank Capacity:</span></b><span data-contrast="auto"> 2.0ML</span><span data-ccp-props="{}"> </span></li>
</ul>
<ul>
 	<li aria-setsize="-1" data-leveltext="●" data-font="Arial" data-listid="1" data-list-defn-props="{&quot;335552541&quot;:1,&quot;335559685&quot;:720,&quot;335559991&quot;:360,&quot;469769226&quot;:&quot;Arial&quot;,&quot;469769242&quot;:[8226],&quot;469777803&quot;:&quot;left&quot;,&quot;469777804&quot;:&quot;●&quot;,&quot;469777815&quot;:&quot;multilevel&quot;}" data-aria-posinset="2" data-aria-level="1"><b><span data-contrast="auto">Battery Capacity:</span></b><span data-contrast="auto"> 200mAh</span><span data-ccp-props="{}"> </span></li>
</ul>
<ul>
 	<li aria-setsize="-1" data-leveltext="●" data-font="Arial" data-listid="1" data-list-defn-props="{&quot;335552541&quot;:1,&quot;335559685&quot;:720,&quot;335559991&quot;:360,&quot;469769226&quot;:&quot;Arial&quot;,&quot;469769242&quot;:[8226],&quot;469777803&quot;:&quot;left&quot;,&quot;469777804&quot;:&quot;●&quot;,&quot;469777815&quot;:&quot;multilevel&quot;}" data-aria-posinset="3" data-aria-level="1"><b><span data-contrast="auto">Dimensions:</span></b><span data-contrast="auto"> 78H x 37.5W x 17.5D (mm) / 3.07H x 1.48W x 0.69D (in)</span><span data-ccp-props="{}"> </span></li>
</ul>
<ul>
 	<li aria-setsize="-1" data-leveltext="●" data-font="Arial" data-listid="1" data-list-defn-props="{&quot;335552541&quot;:1,&quot;335559685&quot;:720,&quot;335559991&quot;:360,&quot;469769226&quot;:&quot;Arial&quot;,&quot;469769242&quot;:[8226],&quot;469777803&quot;:&quot;left&quot;,&quot;469777804&quot;:&quot;●&quot;,&quot;469777815&quot;:&quot;multilevel&quot;}" data-aria-posinset="4" data-aria-level="1"><b><span data-contrast="auto">Voltage Settings:</span></b><span data-contrast="auto"> 3.0V / 3.2V / 3.4V</span><span data-ccp-props="{}"> </span></li>
</ul>
<ul>
 	<li aria-setsize="-1" data-leveltext="●" data-font="Arial" data-listid="1" data-list-defn-props="{&quot;335552541&quot;:1,&quot;335559685&quot;:720,&quot;335559991&quot;:360,&quot;469769226&quot;:&quot;Arial&quot;,&quot;469769242&quot;:[8226],&quot;469777803&quot;:&quot;left&quot;,&quot;469777804&quot;:&quot;●&quot;,&quot;469777815&quot;:&quot;multilevel&quot;}" data-aria-posinset="5" data-aria-level="1"><b><span data-contrast="auto">Activation:</span></b><span data-contrast="auto"> Inhale Activated</span><span data-ccp-props="{}"> </span></li>
</ul>
<ul>
 	<li aria-setsize="-1" data-leveltext="●" data-font="Arial" data-listid="1" data-list-defn-props="{&quot;335552541&quot;:1,&quot;335559685&quot;:720,&quot;335559991&quot;:360,&quot;469769226&quot;:&quot;Arial&quot;,&quot;469769242&quot;:[8226],&quot;469777803&quot;:&quot;left&quot;,&quot;469777804&quot;:&quot;●&quot;,&quot;469777815&quot;:&quot;multilevel&quot;}" data-aria-posinset="6" data-aria-level="1"><b><span data-contrast="auto">Charging:</span></b><span data-contrast="auto"> USB-C</span><span data-ccp-props="{}"> </span></li>
</ul>
<ul>
 	<li aria-setsize="-1" data-leveltext="●" data-font="Arial" data-listid="1" data-list-defn-props="{&quot;335552541&quot;:1,&quot;335559685&quot;:720,&quot;335559991&quot;:360,&quot;469769226&quot;:&quot;Arial&quot;,&quot;469769242&quot;:[8226],&quot;469777803&quot;:&quot;left&quot;,&quot;469777804&quot;:&quot;●&quot;,&quot;469777815&quot;:&quot;multilevel&quot;}" data-aria-posinset="7" data-aria-level="1"><b><span data-contrast="auto">Preheat:</span></b><span data-contrast="auto"> Yes</span><span data-ccp-props="{}"> </span></li>
</ul>
&nbsp;',
        'categories' => array (
  0 => 'aio-3-bio-heating',
  1 => 'ccell',
  2 => 'disposable',
),
        'thumbnail' => '2026/01/1_0027-1.png',
        'gallery' => array (
  0 => '2026/01/GemBox-10-2-scaled.png',
  1 => '2026/01/1_0030-1.png',
  2 => '2026/01/1_0036-1.png',
  3 => '2026/01/1_0000-1.png',
  4 => '2026/01/1_0003-1.png',
  5 => '2026/01/1_0009-1.png',
  6 => '2026/01/1_0013-1.png',
  7 => '2026/01/1_0017-1.png',
  8 => '2026/01/1_0020-1.png',
  9 => '2026/01/1_0024-1.png',
  10 => '2026/01/1_0034-1.png',
  11 => '2026/01/GemBox-9-2-scaled.png',
),
        'meta' => array(
            '_price' => '6.81',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '3.75',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$6.81',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$5.67',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$4.72',
            'quantity_limit_4' => '100–999',
            'price_per_unit_4' => '$4.18',
            'quantity_limit_5' => '1,000+',
            'price_per_unit_5' => '$3.75',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '661',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
);

foreach ($new_products as $prod) {
    $sku = isset($prod["meta"]["_sku"]) ? $prod["meta"]["_sku"] : "";
    
    // Check by SKU first (most reliable)
    if (!empty($sku)) {
        $existing_id = wc_get_product_id_by_sku($sku);
        if ($existing_id) {
            mlog("  SKIP (SKU exists): {$prod['slug']} (SKU: $sku, ID: $existing_id)");
            continue;
        }
    }
    
    // Also check by slug
    $existing = get_page_by_path($prod["slug"], OBJECT, "product");
    if ($existing) {
        mlog("  SKIP (slug exists): {$prod['slug']} (ID: {$existing->ID})");
        continue;
    }
    
    // Replace Docker image IDs in content
    $content = hd_replace_image_ids($prod["content"], $docker_to_prod_id);
    
    $post_data = array(
        "post_title"   => $prod["title"],
        "post_name"    => $prod["slug"],
        "post_content" => $content,
        "post_excerpt" => $prod["excerpt"],
        "post_status"  => "publish",
        "post_type"    => "product",
        "post_author"  => 1,
    );
    
    $product_id = wp_insert_post($post_data);
    if (is_wp_error($product_id)) {
        mlog("  ERROR: {$prod['slug']}: " . $product_id->get_error_message());
        continue;
    }
    
    // Set product type
    wp_set_object_terms($product_id, "simple", "product_type");
    
    // Set categories
    if (!empty($prod["categories"])) {
        wp_set_object_terms($product_id, $prod["categories"], "product_cat");
    }
    
    // Set meta
    foreach ($prod["meta"] as $key => $val) {
        update_post_meta($product_id, $key, $val);
    }
    
    // Set thumbnail
    if (!empty($prod["thumbnail"]) && isset($path_to_prod_id[$prod["thumbnail"]])) {
        set_post_thumbnail($product_id, $path_to_prod_id[$prod["thumbnail"]]);
    }
    
    // Set gallery
    if (!empty($prod["gallery"])) {
        $gallery_ids = array();
        foreach ($prod["gallery"] as $gpath) {
            if (isset($path_to_prod_id[$gpath])) {
                $gallery_ids[] = $path_to_prod_id[$gpath];
            }
        }
        if (!empty($gallery_ids)) {
            update_post_meta($product_id, "_product_image_gallery", implode(",", $gallery_ids));
        }
    }
    
    mlog("  CREATED: {$prod['slug']} (ID: $product_id, SKU: $sku)");
}
mlog("");


// ============================================================
// SECTION 5: Update Existing Products
// ============================================================
mlog("--- Section 5: Update Existing Products ---");

$existing_product_updates = array(
    array(
        'slug' => 'ccell-kera',
        'title' => 'CCELL® Kera',
        'content' => 'The CCELL Kera is a full-ceramic cartridge with a proprietary CCELL ceramic heating element optimized for the all-ceramic construction. The entire oil path — body, mouthpiece, center post, and heating element — is ceramic, eliminating all metal contact with oil for the purest possible flavor profile.

The hand-closable snap-fit mouthpiece (60% less capping force than Gen 1 ceramic cartridges) makes the Kera ideal for brands that want full-ceramic purity without requiring a press for assembly. Available in 0.5ml and 1.0ml.

<strong>Key Features:</strong>
<ul>
<li>Full ceramic construction — zero metal oil contact</li>
<li>Proprietary CCELL ceramic heating element</li>
<li>Zirconia ceramic center post</li>
<li>Ceramic airway</li>
<li>1.4&#8486; resistance</li>
<li>510 thread connection</li>
<li>Hand-closable snap-fit mouthpiece (no press required)</li>
<li>4 x &#248;2mm aperture inlets</li>
<li>Available in 0.5ml and 1.0ml</li>
<li>20% larger clouds vs previous ceramic cartridges</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 0.5ml / 1.0ml</li>
<li>Body: Full ceramic</li>
<li>Center Post: Zirconia ceramic</li>
<li>Airway: Ceramic</li>
<li>Resistance: 1.4&#8486;</li>
<li>Closure: Snap-fit (hand-closable)</li>
<li>Thread: 510</li>
<li>Dimensions: 10.5W x 53.1H mm (0.5ml) / 10.5W x 64.6H mm (1.0ml)</li>
</ul>',
        'excerpt' => 'CCELL Kera full-ceramic cartridge with proprietary ceramic heating. Zero metal oil contact, zirconia center post, hand-closable snap-fit. 1.4 ohm, 510 thread.',
        'categories' => array (
  0 => 'ccell-ceramic-evo-max',
  1 => 'ccell',
  2 => 'cartridge',
),
        'meta' => array(
            '_regular_price' => '5.49',
            '_price' => '5.49',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.79',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.79',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$5.49',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.59',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.79',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$3.29',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.79',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$3.29',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.79',
            'wholesale_customer_wholesale_price' => '3.29',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '3.29',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.79',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL Kera full-ceramic cartridge. Zero metal oil contact, ceramic airway, hand-closable snap-fit. 0.5ml & 1.0ml. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '56305',
            '_wc_average_rating' => '4.92',
            '_wc_review_count' => '26',
        ),
    ),
    array(
        'slug' => 'ccell-voca-pro',
        'title' => 'CCELL® Voca Pro 1.0ml Disposable',
        'content' => '

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243318" image_size="original" width="100"]
[ux_image id="243319" image_size="original" width="100"]
[ux_image id="243320" image_size="original" width="100"]
[ux_image id="243321" image_size="original" width="100"]
[ux_image id="243322" image_size="original" width="100"]
[ux_image id="243323" image_size="original" width="100"]
[ux_image id="243324" image_size="original" width="100"]
[ux_image id="243325" image_size="original" width="100"]
[ux_image id="243326" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => '<h4><strong>100% HTE Compatible</strong>
CCELL Voca Pro was created specifically for high terpene extracts! Built with CCELL EVO, our all-new heating technology, Voca Pro vape is capable of bringing out the distinctive aromas and rich flavor profiles of high terpene extracts, allowing for a memorable, true-to-plant experience with every inhale.</h4>
<h4><strong>10-second Preheat</strong>
With its 10-second preheat mode, CCELL Voca Pro is able to heat up your oil in a snap, keeping its temperature optimal before use. Tap CCELL Voca Pro’s button twice to engage preheating and start the flow of smooth, consistent vapor!</h4>
<h4><strong>Safety Simplified</strong>
Prioritizing safety is paramount, and it should be simple to achieve. To ensure CCELL Voca Pro’s safety and to prevent use by minors, simply lock the device by gently pressing the button 5 times. To unlock the device, all you have to do is press the button 5 times in quick succession again.</h4>
*For first-time use, 5 quick button taps will unlock the device.
<h4><strong>One Tank, Two Volumes*</strong>
Voca Pro vape was designed with the flexibility to house 0.5ml or 1ml of oil without altering the size or shape of the external hardware. This allows oil brands to expand their product range in a flash and utilize the same packaging for both available sizes.</h4>
* Alternative 0.5ml mouthpieces available (Special Order only). Email us directly for more info!
<h4><strong>Clog-Free, Worry-Free</strong>
CCELL Voca Pro is the ideal CCELL disposable for anyone looking for a dependable and hassle-free vaping experience. Built with CCELL’s cutting-edge dual air vent design, Voca Pro vape ensures effortless, smooth draws with consistent vapor delivery and no clogs every time.</h4>
<h4><strong>Specifications:</strong></h4>
<ul>
 	<li>Tank Volume: 1.0ML</li>
 	<li>Coil Resistance: 1.5Ω</li>
 	<li>Battery Capacity: 280mAh</li>
 	<li>Dimensions:
<ul>
 	<li>76H x 36.1W x 13D (mm)</li>
 	<li>2.99H x 1.42W x 0.51D (in)</li>
</ul>
</li>
</ul>
<ul>
 	<li>Preheat and child-resistant button</li>
 	<li>0.5ml and 1ml options available at the same size</li>
 	<li>Clog-free dual air vents</li>
 	<li>Inhale activated</li>
 	<li>USB-C charging</li>
 	<li>Food-grade mouthpiece</li>
 	<li>Medical-grade stainless steel center post</li>
 	<li>Available for customization</li>
</ul>',
        'categories' => array (
  0 => 'aio-se-standard',
  1 => 'ccell',
  2 => 'disposable',
),
        'meta' => array(
            '_price' => '6.89',
            '_stock_status' => 'outofstock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '3.20',
            'wprice_text' => 'As low as',
            'wlowest_price' => '3.20',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$6.89',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$5.69',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$4.69',
            'quantity_limit_4' => '100–999',
            'price_per_unit_4' => '$3.65',
            'quantity_limit_5' => '1,000+',
            'price_per_unit_5' => '$3.20',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–999',
            'wprice_per_unit_1' => '$3.65',
            'wquantity_limit_2' => '1,000+ ',
            'wprice_per_unit_2' => '$3.20',
            '_yoast_wpseo_metadesc' => 'Discover Voca Pro, the dependable and hassle-free vape for high terpene extracts. With CCELL\'s dual air vent design, enjoy effortless, smooth draws and consistent vapor delivery.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '12119',
            '_wc_average_rating' => '4.88',
            '_wc_review_count' => '8',
        ),
    ),
    array(
        'slug' => 'ccell-flex-pro',
        'title' => 'CCELL® Flex Pro 1.0ml Disposable',
        'content' => '

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243346" image_size="original" width="100"]
[ux_image id="243347" image_size="original" width="100"]
[ux_image id="243348" image_size="original" width="100"]
[ux_image id="243349" image_size="original" width="100"]
[ux_image id="243350" image_size="original" width="100"]
[ux_image id="243351" image_size="original" width="100"]
[ux_image id="243352" image_size="original" width="100"]
[ux_image id="243353" image_size="original" width="100"]
[ux_image id="243354" image_size="original" width="100"]
[ux_image id="243355" image_size="original" width="100"]
[ux_image id="243356" image_size="original" width="100"]
[ux_image id="243357" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => '<h4><strong>Fully FLEXible for HTE’S</strong></h4>
Flex Pro was created specifically for high terpene extracts! Built with CCELL EVO, our all-new heating technology, Flex Pro is capable of bringing out the distinctive aromas and rich flavor profiles of high terpene extracts, allowing for a memorable, true-to-plant experience with every inhale.
<h4><strong>Creativity Without Boundaries</strong></h4>
If you want your brand to stand out from the crowd, look no further than Flex Pro. Flex Pro provides unlimited customization so your products can easily get the notice they deserve--Wrap your brand’s artwork around the oil window to give it a distinct personality, or give its surface a unique finish for enhanced aesthetics, tactility, and more.
<h4><strong>Clog-Free, Worry-Free</strong></h4>
Flex Pro is the ideal disposable for anyone looking for a dependable and hassle-free vaping experience. Built with CCELL\'s cutting-edge dual air vent design, Flex Pro ensures effortless, smooth draws with consistent vapor delivery and no clogs every time.
<h4><strong>One Size, Two Volumes</strong></h4>
<strong>* </strong>Flex Pro was designed with the flexibility to house 0.5ml or 1ml of oil without altering the size or shape of the external hardware. This allows oil brands to expand their product range in a flash and utilize the same packaging for both available sizes.

* Alternative 0.5ml mouthpieces available (Special Order only). Email us directly for more info!
<h4><strong>Specifications:</strong></h4>
<ul>
 	<li>Tank Volume: 1.0ML</li>
 	<li>Coil Resistance: 1.5Ω</li>
 	<li>Battery capacity: 280mAh</li>
 	<li>Dimensions:
<ul>
 	<li>108.9H x 23W x 11.3D (mm)</li>
 	<li>4.29H x 0.91W x 0.44D (in)</li>
</ul>
</li>
</ul>
<ul>
 	<li>Customizable oil window design</li>
 	<li>Highly customizable surface finish</li>
 	<li>0.5ml and 1ml options available at the same size</li>
 	<li>Clog-free dual air vents</li>
 	<li>Inhale activated</li>
 	<li>USB-C charging</li>
 	<li>Food-grade mouthpiece</li>
 	<li>Medical-grade stainless steel center post</li>
</ul>',
        'categories' => array (
  0 => 'aio-se-standard',
  1 => 'full-gram-disposables',
  2 => 'ccell',
  3 => 'disposable',
),
        'meta' => array(
            '_price' => '5.60',
            '_stock_status' => 'outofstock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '3.00',
            'wprice_text' => 'As low as',
            'wlowest_price' => '$3.00',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$5.60',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$4.65',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$3.85',
            'quantity_limit_4' => '100–999',
            'price_per_unit_4' => '$3.40',
            'quantity_limit_5' => '1,000+',
            'price_per_unit_5' => '$3.00',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–999',
            'wprice_per_unit_1' => '$3.40',
            'wquantity_limit_2' => '1,000+ ',
            'wprice_per_unit_2' => '$3.00',
            '_yoast_wpseo_metadesc' => 'Discover the exceptional Flex Pro, designed for high terpene extracts. Experience true-to-plant flavors with CCELL EVO heating technology. Enjoy clog-free draws, hassle-free vaping.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '24522',
            '_wc_average_rating' => '4.90',
            '_wc_review_count' => '10',
        ),
    ),
    array(
        'slug' => 'pike',
        'title' => 'CCELL® Pike',
        'content' => '&nbsp;

https://youtu.be/VeYxFtDpYAw',
        'excerpt' => '<div class="videomob" style="display: none;"><iframe src="https://www.youtube.com/embed/VeYxFtDpYAw" width="560" height="315" frameborder="0" allowfullscreen="allowfullscreen"></iframe></div>
<p style="text-align: left;">Meet CCELL® Pike, the first <a href="https://hamiltondevices.com/product-category/disposable/">CCELL® disposable vaporizer</a> with the CCELL® pod system. As simple and light as it is, Pike’s performance is not to be underestimated. The built-in pod system powered by CCELL®, offers unparalleled pure flavor and efficacy with great convenience. As a disposable product, Pike needs no maintenance or preparation – always ready to go. The simple, yet classic design, makes customizations for the Pike endless.</p>
The CCELL pike battery helps your vaping experience.
<ul>
 	<li>This pod is designed to work with very thick oil.</li>
 	<li>Once the pod is pressed on, it can not be removed or refilled; making it a one time use.</li>
 	<li>Inhale activated, buttonless technology</li>
 	<li>Disposable CCELL® pod system design</li>
 	<li>Built-in LED indicator</li>
 	<li>Custom color and branding available</li>
 	<li>Pod volume: 0.5ml</li>
 	<li>Press On mouthpiece</li>
 	<li>Battery Capacity: 350mAh</li>
 	<li>Size: 91.2 x 23 x 12mm</li>
</ul>',
        'categories' => array (
  0 => 'ccell',
  1 => 'ccell-pike-disposable',
),
        'meta' => array(
            '_sku' => 'PKB-BK',
            '_regular_price' => '5.99',
            '_price' => '5.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_tax_class' => 'taxable-goods',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '4.16',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-99	',
            'price_per_unit_1' => '$5.99',
            'quantity_limit_2' => '100+	',
            'price_per_unit_2' => '$4.16',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100',
            'wprice_per_unit_1' => '$4.11',
            'wholesale_customer_wholesale_price' => '4.11',
            '_yoast_wpseo_metadesc' => 'See CCELL® Pike on our website! As a disposable product, Pike needs no maintenance or preparation – always ready to go. Check it out!',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '56112',
            '_wc_average_rating' => '4.85',
            '_wc_review_count' => '35',
        ),
    ),
    array(
        'slug' => 'ccell-listo-1-0ml-disposable',
        'title' => 'CCELL® Listo 1.0ml Disposable',
        'content' => '

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243416" image_size="original" width="100"]
[ux_image id="243417" image_size="original" width="100"]
[ux_image id="243418" image_size="original" width="100"]
[ux_image id="243419" image_size="original" width="100"]
[ux_image id="243420" image_size="original" width="100"]
[ux_image id="243421" image_size="original" width="100"]
[ux_image id="243422" image_size="original" width="100"]
[ux_image id="243424" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => '<strong>LARGER STORAGE, SMALLER BODY</strong>

With the Ccell Listo, <a href="https://hamiltondevices.com/product-category/ccell/">CCELL</a>offers you EVERYTHING you could ever ask for in a disposable. The CCELL Listo 1.0ml disposable features a 1.0ml large tank is designed with 350mAh built-in battery and a rechargeable USB port to assure your enjoyment until the last drop. Featuring a transparent visible oil tank, allowing you to know the amount of oil anytime at a glance. So, if you’re looking for compact CCELL disposables devices with great volume, you’ve found them.

The combination of a visible oil tank and compact design that give Ccell Listo a distinctive elegance and allows total customization.

The CCELL Listo 1.0ml disposable engineered with medical-grade 316L Stainless Steel materials ensures every inhale is secure and delightful. Born to be your personal companion, Listo features an LED indicator light for you to get notified of its status simply at a quick glance.
<ul>
 	<li>The combination of a visible oil tank and compact design that give Listo a distinctive elegance and allows total customization.</li>
 	<li>It’s 350mAh battery with a handy rechargeable feature, double guarantees for long lasting sessions till the last drop for a ZERO WASTE and effortless experience.</li>
 	<li>Listo’s interior engineered with medical-grade 316L Stainless Steel materials ensures every inhale secure and delightful.</li>
 	<li>Born to be your personal companion, Listo features a LED indicator light for you to get notified of its status simply at a quick glance.</li>
</ul>
<strong>Specifications:</strong>
<ul>
 	<li>Tank Volume: 1.0ml</li>
 	<li>Battery Capacity: 350mAh</li>
 	<li>Inhale Activated</li>
 	<li>Charging: Micro-USB Charging Enabled</li>
 	<li>Dimensions: 97.8H*22.3W*10.8D (mm)</li>
 	<li>Compact Visible Oil Tank Design</li>
 	<li>LED Indicator Light Feedback</li>
 	<li>Available for Customization</li>
 	<li>Medical-Grade 316L Stainless Steel Internals</li>
 	<li>Food-Grade PCTG (FDA-approved) Mouthpiece</li>
</ul>
&nbsp;',
        'categories' => array (
  0 => 'aio-se-standard',
  1 => 'full-gram-disposables',
  2 => 'ccell',
  3 => 'disposable',
),
        'meta' => array(
            '_sku' => 'DS2710-U',
            '_price' => '6.45',
            '_stock_status' => 'outofstock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.80',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.80',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$6.45',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$5.35',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$4.65',
            'quantity_limit_4' => '100–999',
            'price_per_unit_4' => '$3.20',
            'quantity_limit_5' => '1,000+',
            'price_per_unit_5' => '$2.80',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–999',
            'wprice_per_unit_1' => '$3.20',
            'wquantity_limit_2' => '1,000+ ',
            'wprice_per_unit_2' => '$2.80',
            '_yoast_wpseo_metadesc' => 'With the Listo, CCELL offers you EVERYTHING you could ever ask for in a disposable. The 1.0ml large tank is designed with 350mAh built-in battery and a rechargeable USB port to assure your enjoyment until the last drop. Featuring a transparent visible oil tank, allowing you to know the amount of oil anytime at a glance.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '178357',
            '_wc_average_rating' => '4.85',
            '_wc_review_count' => '47',
        ),
    ),
    array(
        'slug' => 'ccell-ridge-2-0ml-disposable',
        'title' => 'CCELL Ridge 2.0ml Disposable',
        'content' => '',
        'excerpt' => '<h4><strong>2.0 Volume, 2.0 Design</strong></h4>
A more economical solution is in demand, and we have a solution. This 2ml large tank disposable Ridge vape is a breath of fresh air, featuring a futuristic design like nothing you’ve seen before.
<h4><strong>Large Oil Capacity</strong></h4>
Catering to those who have large cravings, Ridge vape was thoughtfully designed with a 2ml oil tank and a large oil view window, ensuring that no precious oil goes to waste.
<h4><strong>Thoroughly Durable</strong></h4>
Ridge comes with a full metal body and an axis structural design that is exceptionally impact-resistant. Durable through and through, a micro-USB rechargeable battery is in place, allowing Ridge to last until every drop of oil is exhausted.
<h4><strong>Specifications:</strong></h4>
* Tank Volume: 2.0ml

* Battery Capacity: 300mAh

* Dimensions: 105H x 22W x 10D(mm)

* Materials:

- Mouthpiece: Food-grade PCTG

- Oil Tanks: PA

- Center Post: Medical grade stainless steel

- Body: Aluminum alloy

* Full metal housing

* Visible oil tank

* LED indicator

* Inhale activated

* Micro-USB charging

* Available for customization',
        'categories' => array (
  0 => 'aio-se-standard',
  1 => 'ccell',
  2 => 'disposable',
),
        'meta' => array(
            '_sku' => 'DS4020-UGY',
            '_regular_price' => '6.60',
            '_price' => '6.60',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as ',
            'lowest_price' => '3.40',
            'wprice_text' => 'As low as',
            'wlowest_price' => '3.40',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$6.60',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$5.50',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$4.80',
            'quantity_limit_4' => '100–999',
            'price_per_unit_4' => '$3.70',
            'quantity_limit_5' => '1,000+',
            'price_per_unit_5' => '$3.40',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–999',
            'wprice_per_unit_1' => '$3.70',
            'wquantity_limit_2' => '1,000+ ',
            'wprice_per_unit_2' => '$3.40',
            'wholesale_customer_wholesale_price' => '3.70',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '3.70',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '1000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '3.40',
  ),
),
            '_yoast_wpseo_metadesc' => 'Looking for a reliable and high-performance disposable vape pen? Look no further than the CCELL Ridge 2.0ml Disposable from Hamilton Devices!',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '27886',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '20',
        ),
    ),
    array(
        'slug' => 'ccell-dart',
        'title' => 'CCELL® Dart',
        'content' => '',
        'excerpt' => '<h3>Please contact us for availability at <img class="size-full wp-image-185876" title="wholesale" src="https://hamiltondevices.com/wp-content/uploads/2023/01/wholesale.png" alt="wholesale" width="286" height="21" /></h3>
<p style="text-align: left;">The <a href="https://hamiltondevices.com/educational/choosing-the-best-battery-for-your-ccell-cartridge/">CCELL® Dart vape pod</a> is the first pod system from CCELL®. Through numerous tests, CCELL’s® engineers achieved the perfect balance between high performance and compact design. While the Dart vape pod has consistent vapor production and a pocketable and ergonomic design, the robust power is able to generate 210+ puffs with no recognizable decline in vapor from start to finish. Inhale-activated, self-adapted temperature settings, and a no-button design make the <a href="https://hamiltondevices.com/educational/choosing-the-best-battery-for-your-ccell-cartridge/">CCELL® Dart</a> friendly for all user groups.</p>

<h2>Ccell Dart Battery:</h2>
<ul>
 	<li>High quality circuit board with multiple protections</li>
 	<li>Inhale activated, buttonless technology</li>
 	<li>Stealthy breathing LED indicator</li>
 	<li>Magnetic connection, only fits 0.5ml and 1.0ml CCELL® Dart pods</li>
 	<li>Custom color and branding available</li>
 	<li>Power: 3.2v – 3.6v</li>
 	<li>Battery Capacity: 480mAh</li>
 	<li>Size: 72.5 x 28.7 x 12.5mm</li>
 	<li>Color: Matt Black</li>
 	<li>Rechargeable with Micro-USB Port ( USB charger included )</li>
 	<li>Battery only, pod sold separately</li>
</ul>
<h2>Ccell Dart Pod:</h2>
<ul>
 	<li>This Dart vape pod is designed to work with very thick oil</li>
 	<li>This pod has a press on mouthpiece and can not be removed or refilled after being secured; making it a one time use.</li>
 	<li>This pod will only work with the CCELL® Dart battery</li>
 	<li>Custom color and branding available</li>
 	<li>Plastic housing</li>
 	<li>Pod volume: 0.5ml and 1.0ml</li>
 	<li>Integrated mouthpiece</li>
 	<li>Magnetic connection</li>
 	<li>0.5ml Pod Size: 32.7 x 28.5 x 11.3mm</li>
 	<li>1.0ml Pod Size: 37.8 x 28.5 x 11.3mm</li>
 	<li>Pod only, battery sold separately</li>
</ul>',
        'categories' => array (
  0 => 'ccell',
  1 => 'pod-systems',
),
        'meta' => array(
            '_sku' => 'dart',
            '_stock_status' => 'onbackorder',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_tax_class' => 'taxable-goods',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'quantity_limit_5' => '1000-1999',
            'price_per_unit_5' => '$11.95',
            '_yoast_wpseo_metadesc' => 'The CCELL® Dart is a state-of-the-art vape pen that offers exceptional vapor quality and is designed to deliver a smooth and consistent experience. Discover the future of vaping with the CCELL® Dart.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
            '_wc_average_rating' => '4.67',
            '_wc_review_count' => '3',
        ),
    ),
    array(
        'slug' => 'ccell-bellos',
        'title' => 'Bellos Battery with CR Package',
        'content' => 'https://www.youtube.com/watch?v=uowXd9OV_OM',
        'excerpt' => '<div class="videomob" style="display: none;"><iframe src="https://www.youtube.com/embed/uowXd9OV_OM" width="560" height="315" frameborder="0" allowfullscreen="allowfullscreen"></iframe></div>
<p style="text-align: left;">The <a href="https://hamiltondevices.com/product-category/ccell/">CCELL®</a> Bellos is a pod vaporizer that will whisk you away to a land of relaxation. Ideal for high viscosity extracts, this bellos vape maintains the flavor and integrity of oils. A slight vibration with every inhale provides unique haptic feedback – ensuring you get the perfect hit every time. Vivid custom colors and designs are available for this convenient and durable disposable pod vape.</p>

<h2>Bellos Battery Specifications:</h2>
<ul>
 	<li>High quality circuit board with multiple protections</li>
 	<li>Inhale activated, buttonless technology - Haptic feedback</li>
 	<li>Stealthy breathing LED indicator</li>
 	<li>Magnetic connection, only fits 0.5ml and 1.0ml <a href="https://hamiltondevices.com/product-category/ccell/">CCELL®</a> Bellos pods</li>
 	<li>Custom color and branding available</li>
 	<li>Power: 3.2v – 3.6v</li>
 	<li>Battery Capacity: 350mAh</li>
 	<li>Size: 67.6 x 30 x 12.6mm</li>
 	<li>Color: White with Gray and Black</li>
 	<li>Rechargeable with Micro-USB Port ( USB charger included )</li>
 	<li>Battery only, pod sold separately</li>
</ul>
In the world of vaping, the ccell bellos battery delivers reliable performance with a sleek, modern design.',
        'categories' => array (
  0 => 'ccell',
  1 => 'pod-systems',
),
        'meta' => array(
            '_sku' => 'BP-BK',
            '_regular_price' => '19.25',
            '_price' => '19.25',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '19.25',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '16.59',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '14.79',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '12.40',
            'wholesale_customer_wholesale_price' => '12.40',
            '_yoast_wpseo_metadesc' => 'See CCELL® Bellos on our website!The CCELL® Bellos is a pod vaporizer that will whisk you away to a land of relaxation. Check it out!',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '385',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '4',
        ),
    ),
    array(
        'slug' => 'ccell-palm-pro',
        'title' => 'CCELL® Palm Pro',
        'content' => '<script src="https://fast.wistia.com/embed/medias/o4p5f6kpcb.jsonp" async></script><script src="https://fast.wistia.com/assets/external/E-v1.js" async></script>
<div class="wistia_responsive_padding" style="padding: 56.25% 0 0 0; position: relative;">
<div class="wistia_responsive_wrapper" style="height: 100%; left: 0; position: absolute; top: 0; width: 100%;">
<div class="wistia_embed wistia_async_o4p5f6kpcb seo=true videoFoam=true" style="height: 100%; position: relative; width: 100%;">
<div class="wistia_swatch" style="height: 100%; left: 0; opacity: 0; overflow: hidden; position: absolute; top: 0; transition: opacity 200ms; width: 100%;"><img style="filter: blur(5px); height: 100%; object-fit: contain; width: 100%;" src="https://fast.wistia.com/embed/medias/o4p5f6kpcb/swatch" alt="" aria-hidden="true" /></div>
</div>
</div>
</div>',
        'excerpt' => 'Ccell PALM Pro: The classic battery that defined the vaporizer industry. This exemplary battery’s evolution, Palm Pro vape, brings an elevated experience that defies the ordinary and will forever change the way you vape.

You’ve asked for options, so we provided the solution. Experience your cartridges exactly the way they were meant to be consumed with 3 different voltage settings. Whether you want more flavor or more vapor, CCELL Palm Pro battery is here to cater to your preferences.

For those who appreciate cutting-edge technology, the CCELL Palm Pro offers unparalleled convenience. With the Palm Pro vape battery, you get exceptional performance and longevity in a sleek package. Perfect for enthusiasts who demand the best, the CCELL Palm Pro vaporizer battery is the ultimate upgrade for your vaping sessions.

If you\'re exploring the broader world of Palm pens, the Palm Pro stands at the top with its refined features and modern design, making it an ideal choice for both new users and seasoned vapers alike.

<strong>Specifications:</strong>
<ul>
 	<li>Battery Capacity: 500mAh</li>
 	<li>Dimensions:
<ul>
 	<li>2.26H X 1.65W X 0.54D (in)</li>
 	<li>57.5H X 41.9W X 13.7D (mm)</li>
</ul>
</li>
</ul>
<strong>Features:</strong>
<ul>
 	<li>Standard 510 Thread</li>
 	<li>10-second Preheat Option</li>
 	<li>3 Voltage Settings (2.8/3.2/3.6V)</li>
 	<li>Adjustable Airflow</li>
 	<li>3-bar Battery Status LED</li>
 	<li>Drop-in Magnetic Connection</li>
 	<li>Inhale Activated</li>
 	<li>Type-C Charging</li>
 	<li>Available for Customization</li>
</ul>',
        'categories' => array (
  0 => 'ccell',
  1 => 'batteries',
  2 => 'palm-battery',
  3 => 'deals',
),
        'meta' => array(
            '_price' => '21.89',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            '_yoast_wpseo_metadesc' => 'CCELL® Palm Pro: Classic design, enhanced experience. Enjoy 3 voltage settings, adjustable airflow & preheat function. Delicious flavors & rich clouds on the first puff!',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '5917',
            '_wc_average_rating' => '4.89',
            '_wc_review_count' => '80',
        ),
    ),
    array(
        'slug' => 'ccell-gem-bar-1-0ml',
        'title' => 'CCELL® Gem Bar 1.0ML – Luxurious Clarity and Pure Flavor',
        'content' => '<script src="https://fast.wistia.com/player.js" async></script><script src="https://fast.wistia.com/embed/pgm17jf6cs.js" async type="module"></script><style>wistia-player[media-id=\'pgm17jf6cs\']:not(:defined) { background: center / contain no-repeat url(\'https://fast.wistia.com/embed/medias/pgm17jf6cs/swatch\'); display: block; filter: blur(5px); padding-top:56.25%; }</style> <wistia-player media-id="pgm17jf6cs" aspect="1.7777777777777777"></wistia-player>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243362" image_size="original" width="100"]
[ux_image id="243363" image_size="original" width="100"]
[ux_image id="243364" image_size="original" width="100"]
[ux_image id="243365" image_size="original" width="100"]
[ux_image id="243366" image_size="original" width="100"]
[ux_image id="243367" image_size="original" width="100"]
[ux_image id="243368" image_size="original" width="100"]
[ux_image id="243369" image_size="original" width="100"]
[ux_image id="243370" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'The CCELL Gem Bar is a premium, rechargeable all-in-one vape device with a gem-inspired faceted design, featuring 3.0 Bio-Heating technology, the CCELL Gem Bar provides ultra-low temperature vaporization and smooth, flavorful hits without burnt tastes.

Its postless, cotton-free tank provides complete oil transparency through a large view window, preventing clogs and leaks. Available in 1mL and 2mL capacities, this CCELL Gem Bar model is inhale-activated with USB-C charging.
<h4><strong>Key Features</strong></h4>
<ul>
 	<li>Luxurious faceted oil tank design resembling a gem for elegant aesthetics.</li>
 	<li>Large oil view window and postless structure for unobstructed, panoramic oil visibility.</li>
 	<li>CCELL 3.0 Bio-Heating core for ultra-low temperature vapor, preserving terpenes and eliminating burnt flavors.</li>
 	<li>Cotton-free tank to prevent leaks, clogs, and ensure consistent performance.</li>
 	<li>Inhale-activated with indicator lights for effortless operation.</li>
 	<li>USB-C rechargeable for convenient, extended use.</li>
</ul>
<h4><strong>Specifications</strong></h4>
<ul>
 	<li>Reservoir Volume: 1mL or 2mL</li>
 	<li>Dimensions: 98.2mm H x 22mm W x 15.4mm D (3.87in H x 0.87in W x 0.61in D)</li>
 	<li>Battery Capacity: 265mAh</li>
 	<li>Heating Core: CCELL 3.0 Bio-Heating</li>
 	<li>Activation: Inhale-activated with indicator lights</li>
 	<li>Charging: USB-C</li>
</ul>',
        'categories' => array (
  0 => 'aio-3-bio-heating',
  1 => 'ccell',
  2 => 'disposable',
),
        'meta' => array(
            '_sku' => 'DS7310-U',
            '_regular_price' => '5.60',
            '_price' => '5.60',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '3.14',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$5.60 ',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$4.65 ',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$3.85 ',
            'quantity_limit_4' => '100–999',
            'price_per_unit_4' => '$3.40 ',
            'quantity_limit_5' => '1,000+',
            'price_per_unit_5' => '$3.14 ',
            'wholesale_customer_wholesale_price' => '3.04',
            '_yoast_wpseo_metadesc' => 'Tailored for taste, the CCELL® Gem Bar 1.0ml enhances flavor purity and visual clarity, giving U.S. vapers a premium and refined experience.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '3167',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-gem-bar-2-0ml',
        'title' => 'CCELL® Gem Bar 2.0ML – Luxurious Clarity and Pure Flavor',
        'content' => '<script src="https://fast.wistia.com/player.js" async></script><script src="https://fast.wistia.com/embed/pgm17jf6cs.js" async type="module"></script>

<style>wistia-player[media-id=\'pgm17jf6cs\']:not(:defined) { background: center / contain no-repeat url(\'https://fast.wistia.com/embed/medias/pgm17jf6cs/swatch\'); display: block; filter: blur(5px); padding-top:56.25%; }</style>

&nbsp;

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243362" image_size="original" width="100"]
[ux_image id="243363" image_size="original" width="100"]
[ux_image id="243364" image_size="original" width="100"]
[ux_image id="243365" image_size="original" width="100"]
[ux_image id="243366" image_size="original" width="100"]
[ux_image id="243367" image_size="original" width="100"]
[ux_image id="243368" image_size="original" width="100"]
[ux_image id="243369" image_size="original" width="100"]
[ux_image id="243370" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'The CCELL Gem Bar is a premium, rechargeable all-in-one vape device with a gem-inspired faceted design, featuring 3.0 Bio-Heating technology for ultra-low temperature vaporization and smooth, flavorful hits without burnt tastes.

Its postless, cotton-free tank provides complete oil transparency through a large view window, preventing clogs and leaks. Available in 1mL and 2mL capacities, it\'s inhale-activated with USB-C charging.
<h4><strong>Key Features</strong></h4>
<ul>
 	<li>Luxurious faceted oil tank design resembling a gem for elegant aesthetics.</li>
 	<li>Large oil view window and postless structure for unobstructed, panoramic oil visibility.</li>
 	<li>CCELL 3.0 Bio-Heating core for ultra-low temperature vapor, preserving terpenes and eliminating burnt flavors.</li>
 	<li>Cotton-free tank to prevent leaks, clogs, and ensure consistent performance.</li>
 	<li>Inhale-activated with indicator lights for effortless operation.</li>
 	<li>USB-C rechargeable for convenient, extended use.</li>
</ul>
<h4><strong>Specifications</strong></h4>
<ul>
 	<li>Reservoir Volume: 1mL or 2mL</li>
 	<li>Dimensions: 98.2mm H x 22mm W x 15.4mm D (3.87in H x 0.87in W x 0.61in D)</li>
 	<li>Battery Capacity: 265mAh</li>
 	<li>Heating Core: CCELL 3.0 Bio-Heating</li>
 	<li>Activation: Inhale-activated with indicator lights</li>
 	<li>Charging: USB-C</li>
</ul>',
        'categories' => array (
  0 => 'aio-3-bio-heating',
  1 => 'ccell',
  2 => 'disposable',
),
        'meta' => array(
            '_sku' => 'DS7320-U',
            '_regular_price' => '5.70',
            '_price' => '5.70',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '3.24',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$5.70 ',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$4.75 ',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$3.95 ',
            'quantity_limit_4' => '100–999',
            'price_per_unit_4' => '$3.50 ',
            'quantity_limit_5' => '1,000+',
            'price_per_unit_5' => '$3.24 ',
            'wholesale_customer_wholesale_price' => '3.14',
            '_yoast_wpseo_metadesc' => 'Taste the new CCELL® Gem Bar 2.0ml enhances flavor purity and visual clarity, giving U.S. vapers a premium and refined experience.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '2376',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'tombstone-v2',
        'title' => 'Tombstone V2',
        'content' => '<script src="https://fast.wistia.com/embed/medias/cigdda8l5f.jsonp" async></script><script src="https://fast.wistia.com/assets/external/E-v1.js" async></script><div class="wistia_responsive_padding" style="padding:177.78% 0 0 0;position:relative;"><div class="wistia_responsive_wrapper" style="height:100%;left:0;position:absolute;top:0;width:100%;"><div class="wistia_embed wistia_async_cigdda8l5f seo=true videoFoam=true" style="height:100%;position:relative;width:100%"><div class="wistia_swatch" style="height:100%;left:0;opacity:0;overflow:hidden;position:absolute;top:0;transition:opacity 200ms;width:100%;"><img src="https://fast.wistia.com/embed/medias/cigdda8l5f/swatch" style="filter:blur(5px);height:100%;object-fit:contain;width:100%;" alt="" aria-hidden="true" onload="this.parentNode.style.opacity=1;" /></div></div></div></div>',
        'excerpt' => 'This dual 510 thread battery is engineered specifically for CCELL® cartridges but will also work with most 510 thread cartridges. Its innovative design ensures that it can handle double cartridge vape battery requirements effortlessly. Whether you’re looking for a double cart battery or a dual 510 vape battery, this device delivers unmatched performance and versatility. Please see below for product details:

<strong>PACKAGE CONTENTS:</strong>

1* Tombstone V2

2* Wax Coils

1* Dummy Plug Adapter

1* Type-C Cable Charger

1* Dab Tool

2* O-rings

1* Warranty Card

1* Manual

<strong>SPECIFICATIONS:</strong>

Inhale / Button Activated

Battery Capacity: 650mAh

Connector: Screw-in 510 Thread

Adjustable Voltage: Blue (2.8V), White (3.2V), Red (3.8V)

Resistance Range: ≥0.5Ω

Charging: Type-C

Charging Voltage/Current: 5V/450mA

Charging Time: 1.5 hours

Size: 47 (L) x 24.5 (W) x 99mm (H)

*Cartridge not included

<strong>MAINTENANCE TIPS:</strong> Any device dealing with oils has the potential to leak. Regular cleaning will keep the device at its optimal performance.

<strong>RECOMMENDED CLEANING: </strong>* Do not use rubbing alcohol on the exterior of the device. Only applies to the heating plate(s) ( device and cartridge connection point ), cartridge/cartridge pin area, and the interior of the cover if necessary. If the exterior needs to be cleaned, please use a wash cloth or paper towel with a bit of warm water',
        'categories' => array (
  0 => '250th-anniversary',
  1 => 'vaporizers',
  2 => 'deals',
),
        'meta' => array(
            '_price' => '44.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            '_yoast_wpseo_metadesc' => 'The upgraded Tombstone V2 is here! 3 voltage settings, 2 wax coils for concentrate vaping, and dual flavor mixing. Engineered for CCELL® and 510 cartridges.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '2179',
            '_wc_average_rating' => '4.92',
            '_wc_review_count' => '38',
        ),
    ),
    array(
        'slug' => 'cloak-v2',
        'title' => 'Cloak V2',
        'content' => '<script src="https://fast.wistia.com/embed/medias/fajuth3oyb.jsonp" async></script><script src="https://fast.wistia.com/assets/external/E-v1.js" async></script><div class="wistia_responsive_padding" style="padding:177.78% 0 0 0;position:relative;"><div class="wistia_responsive_wrapper" style="height:100%;left:0;position:absolute;top:0;width:100%;"><div class="wistia_embed wistia_async_fajuth3oyb seo=true videoFoam=true" style="height:100%;position:relative;width:100%"><div class="wistia_swatch" style="height:100%;left:0;opacity:0;overflow:hidden;position:absolute;top:0;transition:opacity 200ms;width:100%;"><img src="https://fast.wistia.com/embed/medias/fajuth3oyb/swatch" style="filter:blur(5px);height:100%;object-fit:contain;width:100%;" alt="" aria-hidden="true" onload="this.parentNode.style.opacity=1;" /></div></div></div></div>',
        'excerpt' => 'Attention! Attention! Our Hamilton Devices Cloak V2 just received a makeover. We listened to the People and they demanded more power! This upgraded Cloak V2 vape offers 3 voltage settings you can control with a simple button. But wait, it doesn’t stop there! Our Hamilton Cloak V2 also includes a Wax coil, meaning you can vape concentrate now! Whether you inhale or press the button, you will still get an unparalleled vaping experience.

This Cloak vape battery is engineered specifically for CCELL® cartridges but will also work with most 510 thread cartridges.

<strong>SPECIFICATIONS:</strong>

Inhale / Button Activated

Battery Capacity: 650mAh

Connector: Screw-in 510 Thread

Adjustable Voltage: Blue (2.8V), White (3.2V), Red (3.8V)

Resistance Range: ≥1.0Ω

Charging: Type-C

Charging Voltage/Current: 5V/450mA

Charging Time: 1.5 hours

Size: 35.8 (L) x 22.2 (W) x 94.5mm (H)

<strong>PACKAGE CONTENTS:</strong>

1* Cloak V2

1* Wax Coil

1* Type-C Cable Charger

1* Dab Tool

2* O-rings

1* Warranty Card

1* Manual

*Cartridge not included

<strong>MAINTENANCE TIPS:</strong> Any device dealing with oils has the potential to leak. Regular cleaning will keep the device at its optimal performance.

<strong>RECOMMENDED CLEANING:</strong> * Do not use rubbing alcohol on the exterior of the device. Only applies to the heating plate(s) ( device and cartridge connection point ), cartridge/cartridge pin area, and the interior of the cover if necessary. If the exterior needs to be cleaned, please use a wash cloth or paper towel with a bit of warm water

&nbsp;',
        'categories' => array (
  0 => '250th-anniversary',
  1 => 'vaporizers',
  2 => 'deals',
),
        'meta' => array(
            '_price' => '39.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            '_yoast_wpseo_metadesc' => 'The upgraded Cloak V2: Now with 3 voltage settings, a wax coil for concentrates, and an unparalleled vaping experience. Engineered for CCELL® and 510 cartridges.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '1928',
            '_wc_average_rating' => '4.75',
            '_wc_review_count' => '20',
        ),
    ),
    array(
        'slug' => 'daypipe',
        'title' => 'DAYPIPE',
        'content' => '<script src="https://fast.wistia.com/embed/medias/xptpn19dgl.jsonp" async></script><script src="https://fast.wistia.com/assets/external/E-v1.js" async></script>
<div class="wistia_responsive_padding" style="padding: 177.22% 0 0 0; position: relative;">
<div class="wistia_responsive_wrapper" style="height: 100%; left: 0; position: absolute; top: 0; width: 100%;">
<div class="wistia_embed wistia_async_xptpn19dgl seo=true videoFoam=true" style="height: 100%; position: relative; width: 100%;">
<div class="wistia_swatch" style="height: 100%; left: 0; opacity: 0; overflow: hidden; position: absolute; top: 0; transition: opacity 200ms; width: 100%;"><img style="filter: blur(5px); height: 100%; object-fit: contain; width: 100%;" src="https://fast.wistia.com/embed/medias/xptpn19dgl/swatch" alt="" aria-hidden="true" /></div>
</div>
</div>
</div>',
        'excerpt' => 'Hamilton Devices Daypipe is a revolutionary dry herb pipe. The Daypipe has an ingenious design that enables you to chamber and deploy 8 – 0.2g bowls (1.6 grams total!), all while being durable and reliable. Simply load up the ultra-portable Daypipe and enjoy fresh bowls all day long without any ashy messes in between sessions. This mechanical dry herb pipe delivers true on the go convenience—no batteries, coils or charging ever required..

Daypipe’s twist to advance carousel lets you pre pack eight 0.2 g bowls (1.6 g total). Just twist, apply a flame and inhale—fresh bowls all day, no mess.
<ul>
 	<li>Anodized Stainless Steel Housing</li>
 	<li>No batteries – just light and inhale.</li>
 	<li>Custom color and branding available</li>
 	<li>Capacity: 8 - 0.2g bowls</li>
 	<li>Size: 129.9mm(L)*23.2mm(W)*23.2mm(H)</li>
 	<li>Colors: Graphite, Red</li>
</ul>
<strong>MAINTENANCE TIPS:</strong> General cleaning maintenance will keep the device at its optimal performance.
<strong>RECOMMENDED CLEANING:</strong> Depending on usage, clean as needed by soaking / submerging in rubbing alcohol for a couple of minutes, then use the included brush to scrub out any residual resin build up.',
        'categories' => array (
  0 => 'dry-herbs',
  1 => 'pipes',
),
        'meta' => array(
            '_sku' => 'DP',
            '_price' => '49.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '10-399',
            'wprice_per_unit_1' => '$25.42',
            'wquantity_limit_2' => '400-2999',
            'wprice_per_unit_2' => '$23.42',
            '_yoast_wpseo_metadesc' => 'Pre pack eight 0.2 g bowls and enjoy fresh flower all day. 100 % mechanical—no battery, no charging, just light and inhale.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '3377',
            '_wc_average_rating' => '4.87',
            '_wc_review_count' => '130',
        ),
    ),
    array(
        'slug' => 'draco-dry-flower-combustion',
        'title' => 'DRACO™ – The Pinnacle of Dry Flower Combustion',
        'content' => '<script src="https://fast.wistia.com/embed/medias/qw294d6xjp.jsonp" async></script><script src="https://fast.wistia.com/assets/external/E-v1.js" async></script>
<div class="wistia_responsive_padding" style="padding: 51.67% 0 0 0; position: relative;">
<div class="wistia_responsive_wrapper" style="height: 100%; left: 0; position: absolute; top: 0; width: 100%;">
<div class="wistia_embed wistia_async_qw294d6xjp seo=true videoFoam=true" style="height: 100%; position: relative; width: 100%;">
<div class="wistia_swatch" style="height: 100%; left: 0; opacity: 0; overflow: hidden; position: absolute; top: 0; transition: opacity 200ms; width: 100%;">

<img style="filter: blur(5px); height: 100%; object-fit: contain; width: 100%;" src="https://fast.wistia.com/embed/medias/qw294d6xjp/swatch" alt="" aria-hidden="true" />

</div>
</div>
</div>
</div>
<script src="https://fast.wistia.com/embed/medias/vp1rbiac4u.jsonp" async></script><script src="https://fast.wistia.com/assets/external/E-v1.js" async></script>
<div class="wistia_responsive_padding" style="padding: 180.0% 0 0 0; position: relative;">
<div class="wistia_responsive_wrapper" style="height: 100%; left: 0; position: absolute; top: 0; width: 100%;">
<div class="wistia_embed wistia_async_vp1rbiac4u seo=true videoFoam=true" style="height: 100%; position: relative; width: 100%;">
<div class="wistia_swatch" style="height: 100%; left: 0; opacity: 0; overflow: hidden; position: absolute; top: 0; transition: opacity 200ms; width: 100%;"><img style="filter: blur(5px); height: 100%; object-fit: contain; width: 100%;" src="https://fast.wistia.com/embed/medias/vp1rbiac4u/swatch" alt="" aria-hidden="true" /></div>
</div>
</div>
</div>
&nbsp;

<img class="aligncenter" src="https://hamiltondevices.com/wp-content/uploads/2024/04/Draco-Instructions.jpeg" alt="Draco Instructions" width="484" height="1025" />',
        'excerpt' => '<strong>Discover the DRACO Dry Herb Vaporizer</strong>

Elevate your vaping with the DRACO dry herb vaporizer, a game-changer in portable vape technology. Designed for enthusiasts and beginners alike, the DRACO combines advanced combustion with a sleek, modern design to deliver the pure, rich flavors of your dry herbs.

Enjoy a smooth, satisfying experience with every hit, thanks to its innovative technology that preserves the natural essence of your herbs. Whether you’re seeking the best dry herb vaporizer for on-the-go use or a reliable device for home, the DRACO offers unmatched quality and performance.

Explore the future of vaping today with the DRACO vape—your perfect companion for a premium dry flower experience.

<strong><em>Shop now to experience the DRACO difference!</em></strong>',
        'categories' => array (
  0 => '250th-anniversary',
  1 => 'dry-herbs',
  2 => 'vaporizers',
  3 => 'deals',
),
        'meta' => array(
            '_price' => '189.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            '_yoast_wpseo_metadesc' => 'Experience full flavor dry flower combustion anywhere. DRACO packs a 900 mAh USB C battery, self propelling air pump and 0.5 g chamber.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '295',
            '_wc_average_rating' => '4.80',
            '_wc_review_count' => '10',
        ),
    ),
    array(
        'slug' => 'auxo-celsius-2-0',
        'title' => 'AUXO Celsius 2.0',
        'content' => '<script src="https://fast.wistia.com/embed/medias/dbp0ve4w21.jsonp" async></script><script src="https://fast.wistia.com/assets/external/E-v1.js" async></script><div class="wistia_responsive_padding" style="padding:56.25% 0 0 0;position:relative;"><div class="wistia_responsive_wrapper" style="height:100%;left:0;position:absolute;top:0;width:100%;"><div class="wistia_embed wistia_async_dbp0ve4w21 seo=true videoFoam=true" style="height:100%;position:relative;width:100%"><div class="wistia_swatch" style="height:100%;left:0;opacity:0;overflow:hidden;position:absolute;top:0;transition:opacity 200ms;width:100%;"><img src="https://fast.wistia.com/embed/medias/dbp0ve4w21/swatch" style="filter:blur(5px);height:100%;object-fit:contain;width:100%;" alt="" aria-hidden="true" onload="this.parentNode.style.opacity=1;" /></div></div></div></div>',
        'excerpt' => '<strong>Exceptional Vapor in Seconds</strong>

As fast as 6 seconds* of pre-heating and you’re ready to embark on a flavorful journey.

With advanced Triple Heating Technology, Celsius heats dry herb and concentrates thoroughly and evenly, releasing their rich aromas and multitudes of effects to their fullest potential.

*Pre-heating duration ranges from 6-30 seconds, depending on the temperature setting chosen.

<strong>Quick Start, Easy Settings</strong>

4 different heat settings and customizable Pro Mode to magnify your experience with dry herb or concentrates.

<img class="size-full wp-image-194855" title="dryherb" src="https://hamiltondevices.com/wp-content/uploads/2023/08/dryherb.jpg" alt="dryherb" width="554" height="196" />

<strong>Pro Mode, for the Pros</strong>

Take absolute control of your session with Pro Mode. With the AUXO App, you’ll be able to customize ad 40-second to 3-minute Heating Curve, enabling more dynamic temperature variations to perfectly cater to your specific tastes.

Sharing your customized heating curve with your friends is just a tap away! Let them replicate your custom experience in an instant.

<strong>The Best of Both Worlds</strong>

Whether you’re in the mood for some classic herb or some magical wax/concentrates, Celsius has got your covered. The expertly engineered concentrate chamber allows for complete separation, making cleaning as easy as ever.

<strong>Stunning Design, Portable Size</strong>

Featuring an ergonomic design and an exquisite metal-crafted body with a sleek finish, Celsius is powerful enough to deliver unparalleled experiences, yet compact enough to be extremely portable.

<strong>360° All-Around Enjoyment</strong>

With a 360° rotatable, medical-grade zirconia mouthpiece and an extended air path specially designed within, Celsius guarantees instant vapor cool-down and extraordinary taste every time.

<strong>Specifications:</strong>

<strong>General</strong>
<ul>
 	<li>Dimensions: 4.49H X 1.78W X 1.31D(in)
<ul>
 	<li>114.1H X 45.1W X 33.3D(mm)</li>
</ul>
</li>
 	<li>Weight: 157.8g</li>
 	<li>Materials: Mouthpiece: Zirconia
<ul>
 	<li>Body: Aluminum alloy</li>
 	<li>Heating Oven: Quartz glass</li>
</ul>
</li>
 	<li>Warranty: 10 Years</li>
 	<li>Color: Black, Gray</li>
</ul>
<strong>Heating Oven</strong>
<ul>
 	<li>Oven Capacity: Dry herb: 0.3g
<ul>
 	<li>Wax/Concentrates: 0.05g</li>
</ul>
</li>
 	<li>Heating System: Conduction, infrared, and convection</li>
 	<li>Heat-up Time: 6-30 seconds</li>
 	<li>Temperature Range: 284-500℉/140-260℃</li>
</ul>
<strong>Battery</strong>
<ul>
 	<li>Battery Type: Lithium</li>
 	<li>Capacity: 1100mAh/7.4V</li>
 	<li>Battery Life: 9 sessions/charge*</li>
 	<li>Charging Time: 90 minutes</li>
 	<li>Calculation based on 3-minute sessions</li>
</ul>',
        'categories' => array (
  0 => 'dry-herbs',
  1 => 'auxo',
),
        'meta' => array(
            '_price' => '249.99',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            '_yoast_wpseo_metadesc' => 'Discover AUXO Celsius 2.0: Exceptional vapor in seconds with Triple Heating Technology, customizable settings, and a stunning, portable design.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '9',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-easy-bar',
        'title' => 'CCELL Easy Bar',
        'content' => '

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243358" image_size="original" width="100"]
[ux_image id="243359" image_size="original" width="100"]
[ux_image id="243360" image_size="original" width="100"]
[ux_image id="243361" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'The CCELL Easy Bar is a compact, rechargeable all-in-one vape device designed for effortless use with distillate concentrates.

Featuring trusted CCELL SE ceramic atomizer technology, it delivers smooth, consistent vapor without clogs or leaks. Available in 0.5mL and 1.0mL capacities, it\'s inhale-activated with USB-C charging for convenience.
<h4><strong>Key Features</strong></h4>
<ul>
 	<li>Rechargeable design with USB-C charging for extended use.</li>
 	<li>CCELL SE ceramic heating core for smooth, clog-resistant vapor and pure flavor preservation.</li>
 	<li>Inhale-activated firing with LED indicator for easy, hassle-free operation.</li>
 	<li>Optimized for high-viscosity distillate oils, preventing flooding or dry hits.</li>
 	<li>Compact and discreet form factor, available in 0.5mL or 1.0mL reservoir volumes without size changes.</li>
</ul>
<h4><strong>Specifications</strong></h4>
<ul>
 	<li>Reservoir Volume: 0.5mL or 1.0mL</li>
 	<li>Dimensions: 99.3mm H x 23mm W x 10.5mm D (3.91in H x 0.91in W x 0.41in D)</li>
 	<li>Heating Core: CCELL SE ceramic atomizer</li>
 	<li>Activation: Inhale-activated with LED indicator</li>
 	<li>Charging: USB-C</li>
 	<li>Material: Engineered ThermoPlastic (ETP)</li>
</ul>',
        'categories' => array (
  0 => 'aio-se-standard',
  1 => 'ccell',
  2 => 'disposable',
),
        'meta' => array(
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            '_yoast_wpseo_metadesc' => 'Perfected for convenience, the CCELL Easy Bar combines sleek form and smooth delivery, ideal for on-the-go vaping across the U.S.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-se-cartridge-with-white-ceramic-mouthpiece',
        'title' => 'CCELL TH210-SE Cartridge with White Ceramic Mouthpiece',
        'content' => 'The CCELL TH210-SE is a 1.0ml glass-body cartridge built on the SE heating platform — the original CCELL ceramic coil that established the industry standard for distillate vaporization. The screw-on ceramic mouthpiece provides a premium feel and secure seal.

The SE platform delivers smooth, consistent vapor at 1.4&#8486; resistance, optimized for distillate formulations. The borosilicate glass body provides full oil visibility while the 510 thread ensures universal battery compatibility.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity with borosilicate glass body</li>
<li>SE ceramic heating element (1.4&#8486;)</li>
<li>Screw-on ceramic mouthpiece</li>
<li>510 thread connection</li>
<li>Full oil visibility</li>
<li>Optimized for distillate formulations</li>
<li>4 x &#248;2mm aperture inlets</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: Borosilicate glass</li>
<li>Heating: SE ceramic element</li>
<li>Resistance: 1.4&#8486;</li>
<li>Mouthpiece: Ceramic (screw-on) — White</li>
<li>Thread: 510</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_image id="243407" image_size="original" width="100"]

<!-- Launch File -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Launch File</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243408" image_size="original" width="100"]
[ux_image id="243409" image_size="original" width="100"]
[ux_image id="243410" image_size="original" width="100"]
[ux_image id="243411" image_size="original" width="100"]
[ux_image id="243412" image_size="original" width="100"]
[ux_image id="243413" image_size="original" width="100"]
[ux_image id="243414" image_size="original" width="100"]
[ux_image id="243415" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'CCELL TH210-SE 1.0ml glass cartridge with SE ceramic heating and white ceramic screw-on mouthpiece. 1.4 ohm, 510 thread, distillate-optimized.',
        'categories' => array (
  0 => 'ccell-easy',
  1 => 'ccell-se',
  2 => 'classic',
  3 => 'ccell',
  4 => 'cartridge',
),
        'meta' => array(
            '_sku' => 'TH210SE-WC',
            '_regular_price' => '3.23',
            '_price' => '3.23',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.50',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.50',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$3.23',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.58',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.06',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$1.65',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.50',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.65',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$1.50',
            'wholesale_customer_wholesale_price' => '1.65',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.65',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.50',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH210-SE 1.0ml glass cartridge with white ceramic mouthpiece. SE heating, 1.4 ohm, 510 thread. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '21388',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '6',
        ),
    ),
    array(
        'slug' => 'ccell-th210-se-cartridge-with-black-ceramic-mouthpiece',
        'title' => 'CCELL TH210-SE Cartridge with Black Ceramic Mouthpiece',
        'content' => 'The CCELL TH210-SE is a 1.0ml glass-body cartridge built on the SE heating platform — the original CCELL ceramic coil that established the industry standard for distillate vaporization. The screw-on ceramic mouthpiece provides a premium feel and secure seal.

The SE platform delivers smooth, consistent vapor at 1.4&#8486; resistance, optimized for distillate formulations. The borosilicate glass body provides full oil visibility while the 510 thread ensures universal battery compatibility.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity with borosilicate glass body</li>
<li>SE ceramic heating element (1.4&#8486;)</li>
<li>Screw-on ceramic mouthpiece</li>
<li>510 thread connection</li>
<li>Full oil visibility</li>
<li>Optimized for distillate formulations</li>
<li>4 x &#248;2mm aperture inlets</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: Borosilicate glass</li>
<li>Heating: SE ceramic element</li>
<li>Resistance: 1.4&#8486;</li>
<li>Mouthpiece: Ceramic (screw-on) — White</li>
<li>Thread: 510</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_image id="243407" image_size="original" width="100"]

<!-- Launch File -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Launch File</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243408" image_size="original" width="100"]
[ux_image id="243409" image_size="original" width="100"]
[ux_image id="243410" image_size="original" width="100"]
[ux_image id="243411" image_size="original" width="100"]
[ux_image id="243412" image_size="original" width="100"]
[ux_image id="243413" image_size="original" width="100"]
[ux_image id="243414" image_size="original" width="100"]
[ux_image id="243415" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'CCELL TH210-SE 1.0ml glass cartridge with SE ceramic heating and black ceramic screw-on mouthpiece. 1.4 ohm, 510 thread, distillate-optimized.',
        'categories' => array (
  0 => 'ccell-easy',
  1 => 'ccell-se',
  2 => 'classic',
  3 => 'ccell',
  4 => 'cartridge',
),
        'meta' => array(
            '_sku' => 'TH210SE-BC',
            '_regular_price' => '3.23',
            '_price' => '3.23',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.50',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.50',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$3.23',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.58',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.06',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$1.65',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.50',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.65',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$1.50',
            'wholesale_customer_wholesale_price' => '1.65',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.65',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.50',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL TH210-SE 1.0ml glass cartridge with black ceramic mouthpiece. SE heating, 1.4 ohm, 510 thread. Wholesale from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '36000',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '7',
        ),
    ),
    array(
        'slug' => 'ccell-th210-se-cartridge-flat-gold-mouthpiece',
        'title' => 'CCELL TH210 SE Cartridge – Flat Gold Mouthpiece',
        'content' => '

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_image id="243407" image_size="original" width="100"]

<!-- Launch File -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Launch File</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243408" image_size="original" width="100"]
[ux_image id="243409" image_size="original" width="100"]
[ux_image id="243410" image_size="original" width="100"]
[ux_image id="243411" image_size="original" width="100"]
[ux_image id="243412" image_size="original" width="100"]
[ux_image id="243413" image_size="original" width="100"]
[ux_image id="243414" image_size="original" width="100"]
[ux_image id="243415" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'Achieve a sleek and dependable vaping experience with this <strong>Special Edition 1 mL</strong> TH2 cartridge, complete with a <strong>flat gold</strong> mouthpiece and durable <strong>glass tank</strong>. Its <strong>threaded design</strong> ensures quick refills and a secure seal, while universal <strong>510</strong> threading pairs effortlessly with most vape batteries.
<h3>Key Features</h3>
<ul>
 	<li><strong>Gold Flat Mouthpiece:</strong> Stylish, corrosion-resistant finish</li>
 	<li><strong>Glass Tank (1 mL):</strong> Preserves flavor and oil purity</li>
 	<li><strong>Threaded Closure:</strong> Simple twist-off cap for no-fuss refilling</li>
 	<li><strong>Ceramic Coil:</strong> Smooth, consistent vapor production</li>
 	<li><strong>Standard 510 Thread:</strong> Wide compatibility with common battery mods</li>
</ul>
<h3>Why You’ll Love It</h3>
<ul>
 	<li>Eye-catching gold mouthpiece for a premium look</li>
 	<li>Larger capacity minimizes refill frequency</li>
 	<li>Tool-free assembly and leak-resistant design</li>
 	<li>Built for everyday use with a robust construction</li>
</ul>
Upgrade your vaping setup with the <strong>CCELL TH2 SE Cartridge – Flat Gold Mouthpiece (1 ML)</strong>—a perfect balance of elegance and reliable performance.',
        'categories' => array (
  0 => 'ccell-easy',
  1 => 'ccell-se',
  2 => 'classic',
  3 => 'ccell',
  4 => 'cartridge',
),
        'meta' => array(
            '_sku' => 'TH210SE‐GF',
            '_regular_price' => '3.23',
            '_price' => '3.23',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.50',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.50',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$3.23',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.58',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.06',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$1.65',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.50',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.65',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$1.50',
            'wholesale_customer_wholesale_price' => '1.65',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.65',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.50',
  ),
),
            '_yoast_wpseo_metadesc' => 'Showcase a blend of sophistication and functionality with the CCELL TH210 SE Cartridge, complete with a polished flat gold mouthpiece.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '2253',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '1',
        ),
    ),
    array(
        'slug' => 'ccell-th205-se-cartridge-flat-gold-mouthpiece',
        'title' => 'CCELL TH205 SE Cartridge - Flat Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'Upgrade your session with a <strong>0.5 mL Special Edition glass cartridge</strong> featuring a sleek, <strong>flat gold</strong> mouthpiece. The threaded design makes refills fast and leak-resistant, while the standard 510 connection works flawlessly with most vape batteries.
<h3>Key Features</h3>
<ul>
 	<li><strong>Gold Flat Mouthpiece:</strong> Comfortable, stylish, and corrosion-resistant</li>
 	<li><strong>Glass Tank (0.5 mL):</strong> Maintains purity and flavor of oils</li>
 	<li><strong>Threaded Top:</strong> Easy to open, seal, and refill without extra tools</li>
 	<li><strong>Ceramic Coil:</strong> Delivers smooth, consistent hits—ideal for thicker oils</li>
 	<li><strong>Universal 510 Threading:</strong> Wide compatibility with popular batteries</li>
</ul>
<h3>Why You’ll Love It</h3>
<ul>
 	<li>Pure, robust flavor thanks to an all-glass reservoir</li>
 	<li>Quick, mess-free refills with a simple screw-on cap</li>
 	<li>Durable construction stands up to everyday use</li>
 	<li>Compact capacity perfect for on-the-go convenience</li>
</ul>
<strong>Elevate your vaping routine</strong> with the CCELL TH205 SE Cartridge - Flat Gold Mouthpiece — where premium materials, user-friendly refills, and a dazzling gold finish come together in one exceptional cartridge.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH205SE‐GF',
            '_regular_price' => '3.13',
            '_price' => '3.13',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.45',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$3.13',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.50',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.00',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$1.60',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.45',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.60',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$1.45',
            'wholesale_customer_wholesale_price' => '1.60',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.60',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.45',
  ),
),
            '_yoast_wpseo_metadesc' => 'Trust in premium quality with the CCELL TH205 SE Cartridge, boasting a resilient flat gold mouthpiece for enhanced airflow and a flawless experience.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '744',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th205-se-cartridge-fluted-gold-mouthpiece',
        'title' => 'CCELL TH205 SE Cartridge – Fluted Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'Indulge in a premium vaping experience with this <strong>Special Edition 0.5 mL</strong> TH2 cartridge, featuring a <strong>fluted gold mouthpiece</strong> and durable <strong>glass reservoir</strong>. Its <strong>threaded</strong> design delivers quick, hassle-free refills, while <strong>510</strong> compatibility ensures broad device support.
<h3>Key Features</h3>
<ul>
 	<li><strong>Fluted Gold Mouthpiece:</strong> Ergonomic shape with an elegant finish</li>
 	<li><strong>Glass Tank (0.5 mL):</strong> Maintains clean flavor profiles</li>
 	<li><strong>Threaded Top:</strong> Twist-on closure for simple, leak-resistant refills</li>
 	<li><strong>Ceramic Coil:</strong> Consistent, full-bodied vapor production</li>
 	<li><strong>Universal 510 Thread:</strong> Works seamlessly with most battery mods</li>
</ul>
<h3>Why You’ll Love It</h3>
<ul>
 	<li>Compact size for travel-ready convenience</li>
 	<li>Stylish gold accent that stands out</li>
 	<li>Tool-free refilling and robust construction</li>
</ul>
Elevate your setup with the <strong>CCELL TH2 SE Cartridge – Fluted Gold Mouthpiece (0.5 mL)</strong>—where comfort, style, and reliable performance come together in one sleek package.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH205SE‐GRF',
            '_regular_price' => '3.13',
            '_price' => '3.13',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.45',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.45',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$3.13',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.50',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.00',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$1.60',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.45',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.60',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$1.45',
            'wholesale_customer_wholesale_price' => '1.60',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.60',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.45',
  ),
),
            '_yoast_wpseo_metadesc' => 'Crafted for excellence, the CCELL TH205 SE Cartridge features a sophisticated fluted gold mouthpiece, delivering both elegance and exceptional airflow.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '417',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-se-cartridge-fluted-gold-mouthpiece',
        'title' => 'CCELL TH210 SE Cartridge – Fluted Gold Mouthpiece',
        'content' => '

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_image id="243407" image_size="original" width="100"]

<!-- Launch File -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Launch File</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243408" image_size="original" width="100"]
[ux_image id="243409" image_size="original" width="100"]
[ux_image id="243410" image_size="original" width="100"]
[ux_image id="243411" image_size="original" width="100"]
[ux_image id="243412" image_size="original" width="100"]
[ux_image id="243413" image_size="original" width="100"]
[ux_image id="243414" image_size="original" width="100"]
[ux_image id="243415" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'Redefine your vaping routine with this <strong>1 mL Special Edition TH2</strong> cartridge, featuring a <strong>fluted gold</strong> mouthpiece and durable <strong>glass reservoir</strong>. Its <strong>threaded</strong> design allows quick, leak‐resistant refills, while <strong>510</strong> threading ensures compatibility with most vape batteries.
<h3>Key Features</h3>
<ul>
 	<li><strong>Fluted Gold Mouthpiece:</strong> Ergonomic design with a luxurious finish</li>
 	<li><strong>Glass Tank (1 mL):</strong> Maintains authentic flavor and aroma</li>
 	<li><strong>Threaded Closure:</strong> Twist on/off for swift refilling</li>
 	<li><strong>Ceramic Coil:</strong> Consistent heating for smooth, satisfying draws</li>
 	<li><strong>Universal 510 Thread:</strong> Pairs well with standard battery mods</li>
</ul>
<h3>Why You’ll Love It</h3>
<ul>
 	<li style="list-style-type: none;">
<ul>
 	<li>Larger capacity means fewer refills</li>
</ul>
</li>
</ul>
<ul>
 	<li>Elegant gold finish elevates your setup</li>
</ul>
<ul>
 	<li>Easy, tool-free assembly</li>
 	<li>Crafted for robust reliability</li>
</ul>
Take your sessions to the next level with the <strong>CCELL TH2 SE Cartridge – Fluted Gold Mouthpiece (1 mL)</strong>—where convenience meets striking design.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH210SE‐GRF',
            '_regular_price' => '3.23',
            '_price' => '3.23',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.50',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.50',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$3.23',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.58',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.06',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$1.65',
            'quantity_limit_5' => '2,000+	',
            'price_per_unit_5' => '$1.50',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.65',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$1.50',
            'wholesale_customer_wholesale_price' => '1.65',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.65',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.50',
  ),
),
            '_yoast_wpseo_metadesc' => 'Experience refined craftsmanship with the CCELL TH210 SE Cartridge, offering a sleek fluted gold mouthpiece for enhanced durability and comfort.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '5348',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '3',
        ),
    ),
    array(
        'slug' => 'ccell-th205-se-cartridge-duckbill-gold-mouthpiece',
        'title' => 'CCELL TH205 SE Cartridge – Duckbill Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'The Special Edition TH205 SE pairs CCELL reliability with an <strong>ergonomic duckbill gold mouthpiece</strong> for a comfortable, confident draw. Enjoy a clear <strong>glass reservoir</strong>, <strong>ceramic coil</strong> smoothness, <strong>510</strong> battery compatibility, and a <strong>threaded</strong> closure that makes refills fast and secure—all wrapped in a polished, premium finish.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_regular_price' => '3.13',
            '_price' => '3.13',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.45',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$3.13',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.50',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.00',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$1.60',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.45',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.60',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$1.45',
            'wholesale_customer_wholesale_price' => '1.60',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.60',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.45',
  ),
),
            '_yoast_wpseo_metadesc' => 'Trust in premium quality with the CCELL TH205 SE Cartridge, boasting a resilient flat gold mouthpiece for enhanced airflow and a flawless experience.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-se-cartridge-duckbill-gold-mouthpiece',
        'title' => 'CCELL TH210 SE Cartridge – Duckbill Gold Mouthpiece',
        'content' => '

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_image id="243407" image_size="original" width="100"]

<!-- Launch File -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Launch File</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243408" image_size="original" width="100"]
[ux_image id="243409" image_size="original" width="100"]
[ux_image id="243410" image_size="original" width="100"]
[ux_image id="243411" image_size="original" width="100"]
[ux_image id="243412" image_size="original" width="100"]
[ux_image id="243413" image_size="original" width="100"]
[ux_image id="243414" image_size="original" width="100"]
[ux_image id="243415" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'Upgrade your setup with the Special Edition TH210 and its <strong>duckbill gold mouthpiece</strong>—a comfortable, wider profile that supports a smooth, steady pull. You get a durable <strong>glass tank</strong>, <strong>ceramic coil</strong> performance, <strong>510</strong> compatibility, and a <strong>threaded</strong> top that makes refills quick and leak‑resistant—all in a refined gold finish.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH210SE‐GF-1',
            '_regular_price' => '3.23',
            '_price' => '3.23',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.50',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.50',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$3.23',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.58',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.06',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$1.65',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.50',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.65',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$1.50',
            'wholesale_customer_wholesale_price' => '1.65',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.65',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.50',
  ),
),
            '_yoast_wpseo_metadesc' => 'Showcase a blend of sophistication and functionality with the CCELL TH210 SE Cartridge, complete with a polished flat gold mouthpiece.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'm6t05-se-cartridge-0-5ml',
        'title' => 'M6T05-SE Cartridge (0.5ML) – Clear Flat Mouthpiece',
        'content' => 'The CCELL M6T05-SE (Special Edition) is a 0.5ml ETP cartridge featuring the CCELL SE ceramic heating element with enhanced design refinements. Combines proven SE platform performance with a premium aesthetic for brands that want reliable hardware with a polished look.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity ETP tank</li>
<li>CCELL SE ceramic heating element</li>
<li>Special Edition design refinements</li>
<li>510 thread connection</li>
<li>Optimized for distillate formulations</li>
<li>1.4&#8486; resistance</li>
<li>4 x &#248;2mm aperture inlets</li>
<li>Shatter-resistant ETP body</li>
<li>Viscosity range: 10,000 – 700,000 cP</li>
</ul>

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_image id="243407" image_size="original" width="100"]

<!-- Launch File -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Launch File</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243408" image_size="original" width="100"]
[ux_image id="243409" image_size="original" width="100"]
[ux_image id="243410" image_size="original" width="100"]
[ux_image id="243411" image_size="original" width="100"]
[ux_image id="243412" image_size="original" width="100"]
[ux_image id="243413" image_size="original" width="100"]
[ux_image id="243414" image_size="original" width="100"]
[ux_image id="243415" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'CCELL M6T05-SE Special Edition 0.5ml ETP cartridge with SE ceramic heating. Proven performance with refined aesthetics.',
        'categories' => array (
  0 => 'ccell-se',
  1 => 'gold-rush',
  2 => 'ccell',
  3 => 'cartridge',
),
        'meta' => array(
            '_sku' => 'M6T05-SE-CFM',
            '_regular_price' => '3.79',
            '_price' => '3.79',
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_visibility' => 'visible',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.79',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.79',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$3.79',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$3.09',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.49',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.19',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.79',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.19',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$1.79',
            'wholesale_customer_wholesale_price' => '2.19',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.19',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.79',
  ),
),
            '_yoast_wpseo_metadesc' => 'CCELL M6T05-SE Special Edition 0.5ml ETP cartridge with SE ceramic. Wholesale pricing from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '1419',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '1',
        ),
    ),
    array(
        'slug' => 'ccell-m6t10-se-cartridge-m6t-black-flat-press-on-mouthpiece',
        'title' => 'CCELL M6T10-SE Cartridge - M6T Black Flat Press-On Mouthpiece',
        'content' => '

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_image id="243407" image_size="original" width="100"]

<!-- Launch File -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Launch File</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243408" image_size="original" width="100"]
[ux_image id="243409" image_size="original" width="100"]
[ux_image id="243410" image_size="original" width="100"]
[ux_image id="243411" image_size="original" width="100"]
[ux_image id="243412" image_size="original" width="100"]
[ux_image id="243413" image_size="original" width="100"]
[ux_image id="243414" image_size="original" width="100"]
[ux_image id="243415" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'Experience the perfect blend of functionality and style with the CCELL M6T10-SE Cartridge pre-fitted with the M6T Black Flat Press-On Mouthpiece. This all-in-one solution delivers a superior vaping experience, ensuring optimal flavor, leak-proof performance, and a sleek aesthetic.

<strong>Key Features:</strong>
<ul>
 	<li><strong>1.0ML Capacity:</strong> Enjoy extended vaping sessions with the generous 1.0ML cartridge capacity.</li>
 	<li><strong>Press-fit:</strong> Arbor Press or Capping Machine needed – cannot be closed by hand</li>
 	<li><strong>Leak-Proof Design:</strong> Vape with confidence, knowing the press-fit design prevents any messy leaks or spills.</li>
 	<li><strong>Clear Tank:</strong> Easily monitor your oil levels and refill when needed.</li>
 	<li><strong>Sleek Black Mouthpiece:</strong> The matte black finish adds a touch of sophistication to your vaping setup.</li>
 	<li><strong>Ergonomic Design:</strong> The flat mouthpiece provides a comfortable and enjoyable vaping experience.</li>
 	<li><strong>Premium Quality:</strong> Crafted with CCELL\'s renowned quality and innovation, guaranteeing a superior vaping experience.</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'M6T10SE-BFM',
            '_regular_price' => '2.91',
            '_price' => '2.91',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.35',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.35',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$2.91',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.33',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$1.86',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$1.49',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.35',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.49',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$1.35',
            'wholesale_customer_wholesale_price' => '1.49',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.49',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.35',
  ),
),
            '_yoast_wpseo_metadesc' => 'The CCELL M6T10-SE Cartridge offers precision and performance, equipped with a black flat press-on mouthpiece.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '5037',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-m6t10-se-cartridge-with-m6t-clear-round-press-on-mouthpiece',
        'title' => 'CCELL M6T10-SE Cartridge with M6T Clear Flat Press-On Mouthpiece',
        'content' => '

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_image id="243407" image_size="original" width="100"]

<!-- Launch File -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Launch File</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243408" image_size="original" width="100"]
[ux_image id="243409" image_size="original" width="100"]
[ux_image id="243410" image_size="original" width="100"]
[ux_image id="243411" image_size="original" width="100"]
[ux_image id="243412" image_size="original" width="100"]
[ux_image id="243413" image_size="original" width="100"]
[ux_image id="243414" image_size="original" width="100"]
[ux_image id="243415" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'CCELL M6T10-SE Cartridge and the CCELL M6T Clear Round Press-On Mouthpiece. Enjoy optimal flavor, leak-proof performance, and a discreet, minimalist design.

<strong>Key Features:</strong>
<ul>
 	<li><strong>1.0ML Capacity:</strong> The generous 1.0ML cartridge capacity ensures extended vaping sessions.</li>
 	<li><strong>Press-fit:</strong> Arbor Press or Capping Machine needed – cannot be closed by hand</li>
 	<li><strong>Leak-Proof Design:</strong> Vape with confidence, knowing the press-fit design prevents any messy leaks or spills.</li>
 	<li><strong>Clear Tank &amp; Mouthpiece:</strong> Easily monitor your oil levels and enjoy a discreet vaping experience.</li>
 	<li><strong>Ergonomic Design:</strong> The round mouthpiece provides a comfortable and familiar vaping experience.</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'M6T10 SE-CFM',
            '_regular_price' => '2.91',
            '_price' => '2.91',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.35',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.35',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$2.91',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.33',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$1.86	',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$1.49	',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.35',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.49',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$1.35',
            'wholesale_customer_wholesale_price' => '1.49',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.49',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.35',
  ),
),
            '_yoast_wpseo_metadesc' => 'The CCELL M6T10-SE Cartridge features a clear round press-on mouthpiece, perfect for flavor enthusiasts.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '10453',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '1',
        ),
    ),
    array(
        'slug' => 'ccell-m6t10-se-cartridge-with-m6t-white-flat-press-on-mouthpiece',
        'title' => 'CCELL M6T10-SE Cartridge with M6T White Flat Press-On Mouthpiece',
        'content' => '

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_image id="243407" image_size="original" width="100"]

<!-- Launch File -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Launch File</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243408" image_size="original" width="100"]
[ux_image id="243409" image_size="original" width="100"]
[ux_image id="243410" image_size="original" width="100"]
[ux_image id="243411" image_size="original" width="100"]
[ux_image id="243412" image_size="original" width="100"]
[ux_image id="243413" image_size="original" width="100"]
[ux_image id="243414" image_size="original" width="100"]
[ux_image id="243415" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => 'Experience the perfect blend of functionality and modern elegance with the CCELL M6T10-SE Cartridge pre-fitted with the M6T White Flat Press-On Mouthpiece.

This cartridge delivers a superior vaping experience, ensuring optimal flavor, leak-proof performance, and a clean, minimalist aesthetic.

<strong>Key Features:</strong>
<ul>
 	<li><strong>1.0ML Capacity:</strong> Enjoy extended vaping sessions with the generous 1.0ML cartridge capacity.</li>
 	<li><strong>Press-fit:</strong> Arbor Press or Capping Machine needed – cannot be closed by hand</li>
 	<li><strong>Leak-Proof Design:</strong> Vape with confidence, knowing the press-fit design prevents any messy leaks or spills.</li>
 	<li><strong>Clear Tank:</strong> Easily monitor your oil levels and refill when needed.</li>
 	<li><strong>Sleek White Mouthpiece:</strong> The pristine white finish adds a touch of modern elegance to your vaping setup.</li>
 	<li><strong>Ergonomic Design:</strong> The flat mouthpiece provides a comfortable and enjoyable vaping experience.</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'M6T10SE-WFM',
            '_regular_price' => '2.91',
            '_price' => '2.91',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.35',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.35',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$2.91',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.33',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$1.86',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$1.49',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.35',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.49',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$1.35',
            'wholesale_customer_wholesale_price' => '1.49',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.49',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.35',
  ),
),
            '_yoast_wpseo_metadesc' => 'The CCELL M6T10-SE Cartridge delivers smooth draws with its white flat press-on mouthpiece.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '4926',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'th210-s-cartridge-with-th2-black-ceramic-screw-on-mouthpiece',
        'title' => 'TH210-S Cartridge with TH2 Black Ceramic Screw-On Mouthpiece',
        'content' => '',
        'excerpt' => 'Indulge in pure flavor and refined design with this premium combination. The CCELL TH210-S 1.0ml Glass Cartridge offers exceptional performance and durability, while the TH2 Black Ceramic Screw-On Mouthpiece adds a touch of sophistication and enhances flavor delivery.
<ul>
 	<li>1.0ML Capacity: Enjoy extended vaping sessions without frequent refills.</li>
 	<li>Premium Glass Construction: Ensures durability and preserves the purity of your oils.</li>
 	<li>Secure Screw-Fit Connection: Provides a leak-proof and hassle-free experience.</li>
</ul>
<ul>
 	<li>Sleek Black Finish: Adds a touch of sophistication to your vaping setup.</li>
 	<li>Ceramic Material: Enhances flavor delivery and provides a smooth draw.</li>
 	<li>Ergonomic Design: Ensures a comfortable vaping experience.</li>
</ul>',
        'categories' => array (
  0 => 'ccell-se',
  1 => 'classic',
  2 => 'ccell',
  3 => 'cartridge',
),
        'meta' => array(
            '_sku' => 'TH210S-BC',
            '_regular_price' => '4.21',
            '_price' => '4.21',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.42',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.42',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.21',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.06',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.23',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$2.56',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.42',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.56',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.42',
            'wholesale_customer_wholesale_price' => '2.66',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.56',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.42',
  ),
),
            '_yoast_wpseo_metadesc' => 'The TH210-S Cartridge pairs functionality with style, featuring a TH2 black ceramic screw-on mouthpiece.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '532',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'th210-s-cartridge-with-th2-white-ceramic-screw-on-mouthpiece',
        'title' => 'TH210-S Cartridge with TH2 White Ceramic Screw-On Mouthpiece',
        'content' => '',
        'excerpt' => 'Experience pure flavor and modern elegance with this premium combination. The CCELL TH210-S 1.0ml Glass Cartridge offers exceptional performance and durability, while the TH2 White Ceramic Screw-On Mouthpiece adds a touch of sophistication and enhances flavor delivery.
<ul>
 	<li>1.0ML Capacity: Enjoy extended vaping sessions without frequent refills.</li>
 	<li>Premium Glass Construction: Ensures durability and preserves the purity of your oils.</li>
 	<li>Secure Screw-Fit Connection: Provides a leak-proof and hassle-free experience.</li>
</ul>
<ul>
 	<li>Sleek White Finish: Adds a touch of modern elegance to your vaping setup</li>
 	<li>Ceramic Material: Enhances flavor delivery and provides a smooth draw</li>
</ul>
Ergonomic Design: Ensures a comfortable vaping experience',
        'categories' => array (
  0 => 'ccell-se',
  1 => 'classic',
  2 => 'ccell',
  3 => 'cartridge',
),
        'meta' => array(
            '_sku' => 'TH210S-WC',
            '_regular_price' => '4.21',
            '_price' => '4.21',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.42',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.42',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.21',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.06',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.23',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$2.56',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.42',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.56',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.42',
            'wholesale_customer_wholesale_price' => '2.66',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.56',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.42',
  ),
),
            '_yoast_wpseo_metadesc' => 'Made for purity, the TH210-S with TH2 ceramic mouthpiece provides clean taste, secure fitting, and dependable oil flow every time.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '2163',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '1',
        ),
    ),
    array(
        'slug' => 'th210-s-cartridge-with-th2-black-plastic-screw-on-mouthpiece',
        'title' => 'TH210-S Cartridge with TH2 Black Plastic Screw-On Mouthpiece',
        'content' => '',
        'excerpt' => 'Enjoy a reliable and functional vaping experience with this practical combination. The CCELL TH210-S 1.0ml Glass Cartridge offers exceptional performance and durability, while the TH2 Black Plastic Screw-On Mouthpiece provides a comfortable and convenient vaping experience.
<ul>
 	<li>1.0ML Capacity: Enjoy extended vaping sessions without frequent refills.</li>
 	<li>Premium Glass Construction: Ensures durability and preserves the purity of your oils.</li>
 	<li>Secure Screw-Fit Connection: Provides a leak-proof and hassle-free experience.</li>
</ul>
<ul>
 	<li>Durable Plastic Construction: Offers a reliable and lightweight vaping experience.</li>
 	<li>Sleek Black Finish: Adds a touch of sophistication to your vaping setup</li>
 	<li>Ergonomic Design: Ensures a comfortable vaping experience</li>
</ul>',
        'categories' => array (
  0 => 'ccell-se',
  1 => 'classic',
  2 => 'ccell',
  3 => 'cartridge',
),
        'meta' => array(
            '_sku' => 'TH210S-BP',
            '_regular_price' => '4.31',
            '_price' => '4.31',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.24',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.24',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.31',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$3.65',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.08',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$2.46',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.24',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.46',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$2.24',
            'wholesale_customer_wholesale_price' => '2.46',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.46',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.24',
  ),
),
            '_yoast_wpseo_metadesc' => 'The TH210-S Cartridge combines durability and precision with its TH2 black plastic screw-on mouthpiece.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '295',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '2',
        ),
    ),
    array(
        'slug' => 'th210-s-with-sandalwood-round-screw-on-mouthpiece',
        'title' => 'TH210-S with Sandalwood Round Screw-On Mouthpiece',
        'content' => '',
        'excerpt' => 'Experience a unique and luxurious vaping experience with this exquisite combination. The CCELL TH210-S 1.0ml Glass Cartridge offers exceptional performance and durability, while the TH2 Sandalwood Round Screw-On Mouthpiece adds a touch of natural beauty and enhances flavor delivery.
<ul>
 	<li>1.0ML Capacity: Enjoy extended vaping sessions without frequent refills</li>
 	<li>Premium Glass Construction: Ensures durability and preserves the purity of your oils</li>
 	<li>Secure Screw-Fit Connection: Provides a leak-proof and hassle-free experience</li>
</ul>
<ul>
 	<li>Natural Sandalwood Mouthpiece: Offers a unique and luxurious vaping experience</li>
 	<li>Smooth Finish: Provides a comfortable and enjoyable draw</li>
 	<li>Exquisite Craftsmanship: Adds a touch of natural beauty to your vaping setup</li>
</ul>',
        'categories' => array (
  0 => 'ccell-se',
  1 => 'classic',
  2 => 'ccell',
  3 => 'cartridge',
),
        'meta' => array(
            '_sku' => 'TH210S-SWR',
            '_regular_price' => '4.61',
            '_price' => '4.61',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.61',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.24',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.60',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$2.88',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.62',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.88',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.88',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.88',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.62',
  ),
),
            '_yoast_wpseo_metadesc' => 'Designed for smoothness, the TH210-S Sandalwood Round pairs classic warmth with flawless airflow. Try it now for a satisfying vape draw.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '484',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'th210-s-with-sandalwood-screw-on-mouthpiece',
        'title' => 'TH210-S with Sandalwood Flat Screw-On Mouthpiece',
        'content' => '',
        'excerpt' => 'Experience a unique and luxurious vaping experience with this exquisite combination. The CCELL TH210-S 1.0ml Glass Cartridge offers exceptional performance and durability, while the TH2 Sandalwood Screw-On Mouthpiece adds a touch of natural beauty and enhances flavor delivery.
<ul>
 	<li>1.0ML Capacity: Enjoy extended vaping sessions without frequent refills</li>
 	<li>Premium Glass Construction: Ensures durability and preserves the purity of your oils</li>
 	<li>Secure Screw-Fit Connection: Provides a leak-proof and hassle-free experience</li>
</ul>
<ul>
 	<li>Natural Sandalwood: Offers a unique and luxurious vaping experience</li>
 	<li>Smooth Finish: Provides a comfortable and enjoyable draw</li>
 	<li>Exquisite Craftsmanship: Adds a touch of natural beauty to your vaping setup</li>
</ul>',
        'categories' => array (
  0 => 'ccell-se',
  1 => 'classic',
  2 => 'ccell',
  3 => 'cartridge',
),
        'meta' => array(
            '_sku' => 'TH210S-SW',
            '_regular_price' => '4.61',
            '_price' => '4.61',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.61',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.24',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.60',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$2.88',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.62',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.88',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.88',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.88',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.62',
  ),
),
            '_yoast_wpseo_metadesc' => 'Crafted for elegance, the TH210-S Sandalwood Flat mouthpiece gives a natural touch with secure fitting. Buy today for a refined vape session.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '1237',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th2-se-2ml',
        'title' => 'TH2-SE 2ml',
        'content' => '',
        'excerpt' => '<h2 style="text-align: left; color: #cc202c; font-weight: 900;"><strong>Coming soon</strong></h2>
<strong>TH2-SE 2ml</strong>

Built for extended satisfaction, the TH2-SE 2ml cartridge offers a higher oil capacity and superior quality with every draw.

<strong>Unmatched Ceramic Heating Technology</strong>

Leveraging CCELL’s proprietary ceramic heating technology, the TH2-SE 2ml ensures the best-in-class taste and a consistently satisfying experience. Its advanced design and state-of-the-art engineering guarantee an exceptional quality for all consumers.

<strong>Consistency and Reliability</strong>
Our advanced engineering and stringent quality control protocols maximize consistency across all our products. With over 530 million CCELL cartridges shipped and used, the TH2-SE 2ml stands as a testament to our commitment to quality and reliability.

<strong>100% Compliant, Zero Compromises</strong>
The TH2-SE 2ml is meticulously crafted to meet all industry regulations, providing a safe and worry-free vaping experience. Consumers can indulge in the exceptional flavor and quality with complete peace of mind.

<strong>Large Tank for Prolonged Use</strong>
Featuring a 2ml oil capacity, the TH2-SE 2ml cartridge offers prolonged use and a more consistent vaping experience over time, ensuring long-lasting satisfaction for users.

<strong>Specifications</strong>
<ul>
 	<li>Standard 510 thread</li>
 	<li>Tank Volume: 2ml</li>
 	<li>Dimensions: Φ14 x 60H(mm) / Φ0.55 x 2.4H (in)</li>
 	<li>Resistance: 1.4Ω</li>
 	<li>Aperture Size: 4 x Φ2mm</li>
 	<li>Proprietary Ceramic Heating Element</li>
 	<li>Snap-Fit Mouthpiece</li>
 	<li>Borosilicate Glass Body</li>
 	<li>Medical-Grade Stainless Steel Center Post</li>
 	<li>Available for Customization</li>
</ul>',
        'categories' => array (
  0 => 'ccell-se',
  1 => 'ccell',
  2 => 'cartridge',
),
        'meta' => array(
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            '_yoast_wpseo_metadesc' => 'Discover the TH2-SE 2ml Cartridge, expertly crafted for consistent and efficient vaping.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'th2-se-b-with-white-ceramic-mouthpiece',
        'title' => 'TH2-SE-B with White Ceramic Mouthpiece',
        'content' => '',
        'excerpt' => 'Introducing the TH210-SE-B with White Ceramic Mouthpiece, the latest upgrade in our TH210 series.

This product maintains all the advanced specifications and high standards of the TH210-SE line, with a significant enhancement: a white ceramic mouthpiece designed for a sleek, modern look and comfortable use.

The TH210-SE-B mouthpieces differ from the TH210-SE versions, featuring an easy press-on design, unlike the original screw-on mouthpiece. This model combines precision engineering with user-centric design, ensuring both performance and style.

<strong>Key Features:</strong>

<strong>Unmatched Ceramic Heating Technology</strong>

Leveraging CCELL’s proprietary ceramic heating technology, the TH210-SE-B ensures the best-in-class taste and a consistently satisfying experience. Its advanced design and state-of-the-art engineering guarantee exceptional quality for all consumers.

<strong>100% Compliant, Zero Compromises</strong>

The TH210-SE-B is meticulously crafted to meet all industry regulations, providing a safe and worry-free vaping experience. Consumers can indulge in the exceptional flavor and quality with complete peace of mind.

<strong>Specifications:</strong>
<ul>
 	<li>Mouthpiece Type: White Ceramic, Easy Press-On</li>
 	<li></li>
 	<li>Center Post: Medical-grade Stainless Steel</li>
 	<li>Body: Borosilicate Glass</li>
 	<li>Tank Volume: 1ml</li>
 	<li>Dimensions: Φ10.60 x 60H(mm) / Φ0.55 x 2.4H (in)</li>
 	<li>Available for Customization.</li>
</ul>',
        'categories' => array (
  0 => 'ccell-se',
  1 => 'classic',
  2 => 'ccell',
  3 => 'cartridge',
),
        'meta' => array(
            '_sku' => 'TH210-SE-B-WC',
            '_regular_price' => '3.87',
            '_price' => '3.87',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.70',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$3.87',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$2.80',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$2.00',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '$1.70',
            'wholesale_customer_wholesale_price' => '1.7',
            '_yoast_wpseo_metadesc' => 'Achieve the ultimate vaping experience with the TH2-SE-B Cartridge, designed with a premium white ceramic mouthpiece.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '6147',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'm6t10-se-b-with-clear-round-mouthpiece',
        'title' => 'M6T10-SE-B with Clear Round Mouthpiece',
        'content' => '

<!-- Product Showcase -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>
[ux_image id="243407" image_size="original" width="100"]

<!-- Launch File -->
<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Launch File</h3>
[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]
[ux_image id="243408" image_size="original" width="100"]
[ux_image id="243409" image_size="original" width="100"]
[ux_image id="243410" image_size="original" width="100"]
[ux_image id="243411" image_size="original" width="100"]
[ux_image id="243412" image_size="original" width="100"]
[ux_image id="243413" image_size="original" width="100"]
[ux_image id="243414" image_size="original" width="100"]
[ux_image id="243415" image_size="original" width="100"]
[/ux_slider]',
        'excerpt' => '<b>BPA-Free Thermoplastic Cartridge</b>

<b>Tank Volume:</b> 1ml
<b>Dimensions:</b> Φ10.5 x 57.4 / 67.9 (mm) | Φ0.41 x 2.26 / 2.67 (in)
<b>Resistance:</b> 1.4Ω
<b>Heating Element:</b> CCELL ceramic
<b>Center Post:</b> Brass
<b>Body:</b> BPA-free thermoplastic

<strong>Unrivaled Ceramic Heating Technology</strong>

Built with CCELL’s proprietary ceramic heating technology, the M6T-SE enables best-in-class taste and unrivaled quality. Its innovative design and state-of-the-art engineering deliver superior quality and an unmatched level of satisfaction to all consumers.

<strong>Maximum Consistency. Across All Product Lines</strong>

CCELL\'s advanced engineering tech and stringent quality control maximize consistency across every product we create and every cloud consumers inhale, as attested to by the 530 million+ CCELL cartridges shipped and used to date.

<strong>100% Compliant. 0 Compromises.</strong>

With meticulous attention to detail, the M6T-SE was crafted to be 100% compliant with industry regulations, ensuring a safe and worry-free vaping experience for all to enjoy. This allows consumers to experience true peace of mind while indulging in exceptional flavor and quality!',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'M6T10-SE-B-CRM',
            '_regular_price' => '5.20',
            '_price' => '5.20',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.26',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.26',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$5.20',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$4.16',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$3.38',
            'quantity_limit_4' => '100-10000',
            'price_per_unit_4' => '$2.26',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '1-19',
            'wprice_per_unit_1' => '$5.20',
            'wquantity_limit_2' => '20-50',
            'wprice_per_unit_2' => '$4.16',
            'wquantity_limit_3' => '51-99',
            'wprice_per_unit_3' => '$3.38',
            'wquantity_limit_4' => '100-10000',
            'wprice_per_unit_4' => '$2.26',
            'wholesale_customer_wholesale_price' => '5.20',
            '_yoast_wpseo_metadesc' => 'Discover precision vaping with the M6T10-SE-B Cartridge, crafted with a clear round mouthpiece.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '2545',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '1',
        ),
    ),
    array(
        'slug' => 'ccell-th210-evo-1-0ml-cartridge-white-ceramic-mouthpiece',
        'title' => 'CCELL TH210-EVO 1.0ml Cartridge - White Ceramic Mouthpiece',
        'content' => '',
        'excerpt' => 'Elevate your vaping sessions to new heights with this premium combination. The CCELL TH210-EVO 1.0ml Cartridge delivers exceptional performance, while the White Ceramic Mouthpiece adds a touch of modern elegance and enhances flavor delivery.

<strong>Key Features:</strong>
<ul>
 	<li><strong>1.0ML Capacity:</strong> Enjoy extended vaping sessions without frequent refills.</li>
 	<li><strong>Advanced Ceramic Heating Technology:</strong> Experience pure, flavorful vapor with every hit.</li>
 	<li><strong>Leak-Proof Design:</strong> Vape worry-free with a secure, leak-resistant connection.</li>
 	<li><strong>Clear Tank:</strong> Easily monitor your oil levels and refill when needed.</li>
 	<li><strong>White Ceramic Mouthpiece:</strong> The sleek white ceramic mouthpiece adds a touch of modern elegance and enhances flavor.</li>
 	<li><strong>Ergonomic Design:</strong> The mouthpiece\'s comfortable shape ensures a pleasant vaping experience.</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH210EVO-WC',
            '_regular_price' => '4.95',
            '_price' => '4.95',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.42',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.42',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.95',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.50',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.60',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$2.88',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.42',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.88',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$2.42',
            'wholesale_customer_wholesale_price' => '2.88',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.88',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.42',
  ),
),
            '_yoast_wpseo_metadesc' => 'The CCELL TH210-EVO Cartridge offers superior performance with its white ceramic mouthpiece.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '3777',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '1',
        ),
    ),
    array(
        'slug' => 'ccell-th210-evo-1-0ml-cartridge-with-th2-bc-black-ceramic-mouthpiece',
        'title' => 'CCELL TH210-EVO 1.0ml Cartridge with TH2-BC Black Ceramic Mouthpiece',
        'content' => '',
        'excerpt' => 'The CCELL TH210-EVO 1.0ml Cartridge delivers exceptional performance, while the TH2-BC Black Ceramic Mouthpiece adds a touch of sophistication and enhances flavor delivery.

<strong>Key Features:</strong>
<ul>
 	<li><strong>1.0ML Capacity:</strong> Enjoy extended vaping sessions without frequent refills.</li>
 	<li><strong>Advanced Ceramic Heating Technology:</strong>Experience pure, flavorful vapor with every hit.</li>
 	<li><strong>Leak-Proof Design:</strong> Vape worry-free with a secure, leak-resistant connection.</li>
 	<li><strong>Clear Tank:</strong> Easily monitor your oil levels and refill when needed.</li>
 	<li><strong>Black Ceramic Mouthpiece:</strong> The sleek black ceramic mouthpiece adds a touch of elegance and enhances flavor.</li>
 	<li><strong>Ergonomic Design:</strong> The mouthpiece\'s comfortable shape ensures a pleasant vaping experience.</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH210EVO-BC',
            '_regular_price' => '4.95',
            '_price' => '4.95',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.42',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.42',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.95',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.50',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.60',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$2.88',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.42',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.88',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$2.42',
            'wholesale_customer_wholesale_price' => '2.88',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.88',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.42',
  ),
),
            '_yoast_wpseo_metadesc' => 'Enjoy unparalleled performance with the CCELL TH210-EVO Cartridge, featuring a TH2-BC black ceramic mouthpiece.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '4904',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '2',
        ),
    ),
    array(
        'slug' => 'th210-evo-1-0ml-cart-with-white-ceramic-mouthpiece',
        'title' => 'TH210 EVO 1.0ml Cart with White Ceramic Mouthpiece',
        'content' => '',
        'excerpt' => '<strong>All-New Heating Technology</strong>

<strong> </strong>Meet CCELL EVO, an enhanced and optimized heating technology expertly crafted to provide a premium sensory experience that will satisfy your cravings for authentic flavors, larger clouds, and maximum consistency.

<strong> </strong><strong>TH2-EVO Specifications:</strong>
<ul>
 	<li>Medical-grade Stainless Steel Center Post</li>
 	<li>Borosilicate Glass Body</li>
 	<li>Tank Volume: 1.0ml/0.5ml</li>
 	<li>Resistance: 1.4Ω</li>
 	<li>Screw On Mouthpiece</li>
 	<li>Connector: 510 Thread</li>
 	<li>Dimension:
<ul>
 	<li>1.0ml: 10.5(D) x 62(H)mm</li>
 	<li>0.5ml: 10.5(D) x 52(H)mm</li>
</ul>
</li>
</ul>
<ul>
 	<li>Aperture Inlet: 4 x Ф2mm</li>
 	<li>Various Mouthpieces Are Available, Please Contact Us for Details.</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH210-EVO-WCM',
            '_regular_price' => '4.79',
            '_price' => '4.79',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.79',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$3.72',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$2.92',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.62',
            '_yoast_wpseo_metadesc' => 'Discover the premium TH210 EVO 1.0ml Cart with a white ceramic mouthpiece. Experience authentic flavors, larger clouds, and consistent satisfaction.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '21556',
            '_wc_average_rating' => '4.92',
            '_wc_review_count' => '12',
        ),
    ),
    array(
        'slug' => 'th210-evo-1-0ml-cart-with-black-ceramic-mouthpiece',
        'title' => 'TH210 EVO 1.0ml Cart with Black Ceramic Mouthpiece',
        'content' => '',
        'excerpt' => '<strong>All-New Heating Technology</strong>

<strong> </strong>Meet CCELL EVO, an enhanced and optimized heating technology expertly crafted to provide a premium sensory experience that will satisfy your cravings for authentic flavors, larger clouds, and maximum consistency.

<strong> </strong><strong>TH2-EVO Specifications:</strong>
<ul>
 	<li>Medical-grade Stainless Steel Center Post</li>
 	<li>Borosilicate Glass Body</li>
 	<li>Tank Volume: 1.0ml/0.5ml</li>
 	<li>Resistance: 1.4Ω</li>
 	<li>Screw On Mouthpiece</li>
 	<li>Connector: 510 Thread</li>
 	<li>Dimension:
<ul>
 	<li>1.0ml: 10.5(D) x 62(H)mm</li>
 	<li>0.5ml: 10.5(D) x 52(H)mm</li>
</ul>
</li>
</ul>
<ul>
 	<li>Aperture Inlet: 4 x Ф2mm</li>
 	<li>Various Mouthpieces Are Available, Please Contact Us for Details.</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH210-EVO-BCM',
            '_regular_price' => '4.79',
            '_price' => '4.79',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.79',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$3.72',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$2.92',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.62',
            '_yoast_wpseo_metadesc' => 'Check out the TH210 Evo 1.0ml Cart with Black Ceramic Mouthpiece from Hamilton Devices. Upgrade your vaping experience today!',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '72051',
            '_wc_average_rating' => '4.86',
            '_wc_review_count' => '22',
        ),
    ),
    array(
        'slug' => 'th210-evo-b-with-black-ceramic-mouthpiece',
        'title' => 'TH210-EVO-B with Black Ceramic Mouthpiece',
        'content' => '',
        'excerpt' => 'Introducing the TH210-EVO-B with Black Ceramic Mouthpiece, the latest upgrade in our TH210 series.

This product maintains all the advanced specifications and high standards of the TH210-EVO line, with a significant enhancement: a black ceramic mouthpiece designed for a sleek, modern look and comfortable use.

The TH210-EVO-B mouthpieces differ from the TH210-EVO versions, featuring an easy press-on design, unlike the original screw-on mouthpiece. This model combines precision engineering with user-centric design, ensuring both performance and style.

<strong>All-New Heating Technology</strong>

Meet CCELL EVO, an enhanced and optimized heating technology expertly crafted to provide a premium sensory experience that will satisfy your cravings for authentic flavors, larger clouds, and maximum consistency.

<strong>Specifications:</strong>
<ul>
 	<li>Mouthpiece Type: Black Ceramic, Easy Press-On</li>
 	<li>Compatibility: Fully compatible with all accessories and components of the TH210-EVO series</li>
 	<li>Center Post: Medical-grade Stainless Steel</li>
 	<li>Body: Borosilicate Glass</li>
 	<li>Tank Volume: 1.0ml</li>
 	<li>Resistance: 1.4Ω</li>
 	<li>Dimensions: 1.0ml: 10.5(D) x 62(H)mm</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH210-EVO-B-BCM',
            '_regular_price' => '4.79',
            '_price' => '4.79',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19',
            'price_per_unit_1' => '$4.79',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$3.72',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$2.92',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.62',
            '_yoast_wpseo_metadesc' => 'Experience the durability of the TH210-EVO-B, equipped with a black ceramic mouthpiece for reliability.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '2135',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'th205-evo-0-5ml-cart-with-white-ceramic-mouthpiece',
        'title' => 'TH205 EVO 0.5ml Cart with White Ceramic Mouthpiece',
        'content' => '',
        'excerpt' => '<strong>All-New Heating Technology</strong>

<strong> </strong>Meet CCELL EVO, an enhanced and optimized heating technology expertly crafted to provide a premium sensory experience that will satisfy your cravings for authentic flavors, larger clouds, and maximum consistency.

<strong> </strong><strong>TH2-EVO Specifications:</strong>
<ul>
 	<li>Medical-grade Stainless Steel Center Post</li>
 	<li>Borosilicate Glass Body</li>
 	<li>Tank Volume: 1.0ml/0.5ml</li>
 	<li>Resistance: 1.4Ω</li>
 	<li>Screw On Mouthpiece</li>
 	<li>Connector: 510 Thread</li>
 	<li>Dimension:
<ul>
 	<li>1.0ml: 10.5(D) x 62(H)mm</li>
 	<li>0.5ml: 10.5(D) x 52(H)mm</li>
</ul>
</li>
</ul>
<ul>
 	<li>Aperture Inlet: 4 x Ф2mm</li>
 	<li>Various Mouthpieces Are Available, Please Contact Us for Details.</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH205-EVO-WCM',
            '_regular_price' => '4.79',
            '_price' => '4.79',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.79',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$3.72',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$2.92',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.62',
            '_yoast_wpseo_metadesc' => 'The TH205 EVO 0.5ml cartridge with a white ceramic mouthpiece from Hamilton Devices is a top-of-the-line vape cartridge that delivers smooth and flavorful hits.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '12307',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '6',
        ),
    ),
    array(
        'slug' => 'th205-evo-0-5ml-cart-with-black-ceramic-mouthpiece',
        'title' => 'TH205 EVO 0.5ml Cart with Black Ceramic Mouthpiece',
        'content' => '',
        'excerpt' => '<strong>All-New Heating Technology</strong>

<strong> </strong>Meet CCELL EVO, an enhanced and optimized heating technology expertly crafted to provide a premium sensory experience that will satisfy your cravings for authentic flavors, larger clouds, and maximum consistency.

<strong> </strong><strong>TH2-EVO Specifications:</strong>
<ul>
 	<li>Medical-grade Stainless Steel Center Post</li>
 	<li>Borosilicate Glass Body</li>
 	<li>Tank Volume: 1.0ml/0.5ml</li>
 	<li>Resistance: 1.4Ω</li>
 	<li>Screw On Mouthpiece</li>
 	<li>Connector: 510 Thread</li>
 	<li>Dimension:
<ul>
 	<li>1.0ml: 10.5(D) x 62(H)mm</li>
 	<li>0.5ml: 10.5(D) x 52(H)mm</li>
</ul>
</li>
</ul>
<ul>
 	<li>Aperture Inlet: 4 x Ф2mm</li>
 	<li>Various Mouthpieces Are Available, Please Contact Us for Details.</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH205-EVO-BCM',
            '_regular_price' => '4.79',
            '_price' => '4.79',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.79',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$3.72',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$2.92',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.62',
            '_yoast_wpseo_metadesc' => 'Experience the ultimate in vaping convenience with the TH205 EVO 0.5ml Cart with Black Ceramic Mouthpiece from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '8638',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '7',
        ),
    ),
    array(
        'slug' => 'ccell-th210-evo-cartridge-flat-gold-mouthpiece',
        'title' => 'CCELL TH210 EVO Cartridge – Flat Gold Mouthpiece',
        'content' => '',
        'excerpt' => '<div>Elevate every draw with this &lt;strong&gt;1 mL TH2 EVO&lt;/strong&gt; cartridge, featuring a stylish &lt;strong&gt;flat gold&lt;/strong&gt; mouthpiece and the latest &lt;strong&gt;EVO ceramic coil&lt;/strong&gt; technology. The threaded glass reservoir makes refills effortless, while standard &lt;strong&gt;510&lt;/strong&gt; compatibility ensures seamless use with most vape batteries.</div>
<div>&lt;h3&gt;Key Features&lt;/h3&gt;</div>
<div>&lt;ul&gt;</div>
<div>  &lt;li&gt;&lt;strong&gt;Gold Flat Mouthpiece:&lt;/strong&gt; A sleek, modern design that’s both comfortable and corrosion-resistant.&lt;/li&gt;</div>
<div>  &lt;li&gt;&lt;strong&gt;Upgraded EVO Coil:&lt;/strong&gt; Engineered to heat oils evenly, delivering pure flavor and robust vapor.&lt;/li&gt;</div>
<div>  &lt;li&gt;&lt;strong&gt;Glass Tank (1 mL):&lt;/strong&gt; Maintains the full aroma and quality of your concentrates.&lt;/li&gt;</div>
<div>  &lt;li&gt;&lt;strong&gt;Threaded Seal:&lt;/strong&gt; Screw-on top prevents leaks and makes for quick, mess-free refills.&lt;/li&gt;</div>
<div>  &lt;li&gt;&lt;strong&gt;Universal 510 Thread:&lt;/strong&gt; Pairs easily with a wide range of battery mods.&lt;/li&gt;</div>
<div>&lt;/ul&gt;</div>
<div>&lt;h3&gt;Why You’ll Love It&lt;/h3&gt;</div>
<div>&lt;ul&gt;</div>
<div>  &lt;li&gt;Clean, consistent taste in every puff&lt;/li&gt;</div>
<div>  &lt;li&gt;Easy maintenance and reliable sealing&lt;/li&gt;</div>
<div>  &lt;li&gt;Spacious 1 mL capacity for fewer refills on the go&lt;/li&gt;</div>
<div>  &lt;li&gt;Durable materials for everyday use&lt;/li&gt;</div>
<div>&lt;/ul&gt;</div>
<div>&lt;strong&gt;Upgrade your setup&lt;/strong&gt; with the &lt;strong&gt;CCELL TH2 EVO Cartridge – Flat Gold Mouthpiece (1 mL)&lt;/strong&gt; and experience superior performance and style in one perfect package.</div>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH210EVO‐GF',
            '_regular_price' => '4.95',
            '_price' => '4.95',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.42',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.42',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.95',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.50',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.60',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.88',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.42',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.88',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$2.42',
            'wholesale_customer_wholesale_price' => '2.88',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.88',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.42',
  ),
),
            '_yoast_wpseo_metadesc' => 'Upgrade your sessions using the CCELL TH210 EVO Cartridge, meticulously crafted with a sleek flat gold mouthpiece for optimal flavor delivery.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '162',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th205-evo-cartridge-flat-gold-mouthpiece',
        'title' => 'CCELL TH205 EVO Cartridge – Flat Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'Enjoy top-tier performance in a compact profile. This <strong>TH2 EVO</strong> cartridge features a <strong>flat gold</strong> mouthpiece and <strong>0.5 mL</strong> glass reservoir, delivering pure flavor through <strong>advanced EVO ceramic</strong> heating technology. Its <strong>threaded</strong> design ensures quick, leak-resistant refills, while <strong>510</strong> compatibility allows use with a wide range of vape batteries.
<h3>Key Features</h3>
<ul>
 	<li><strong>Gold Flat Mouthpiece:</strong> Sleek finish that resists tarnishing</li>
 	<li><strong>EVO Ceramic Coil:</strong> Even heating for smooth, robust vapor</li>
 	<li><strong>Glass Tank (0.5 mL):</strong> Maintains the full taste of your concentrates</li>
 	<li><strong>Threaded Seal:</strong> Twist on/off for hassle-free refills</li>
 	<li><strong>Universal 510 Thread:</strong> Pairs effortlessly with most standard batteries</li>
</ul>
<h3>Why You’ll Love It</h3>
<ul>
 	<li>Compact capacity for portable convenience</li>
 	<li>Leak-resistant threading for a cleaner experience</li>
 	<li>Quick, tool-free refills</li>
 	<li>Premium materials built to last</li>
</ul>
Upgrade your on-the-go sessions with the <strong>CCELL TH2 EVO Cartridge – Flat Gold Mouthpiece (0.5 mL)</strong>, where innovative coil design meets dependable, stylish hardware.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH205EVO‐GF',
            '_regular_price' => '4.79',
            '_price' => '4.79',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.79',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$3.72',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$2.92',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.05',
            '_yoast_wpseo_metadesc' => 'Indulge in top-tier design with the CCELL TH205 EVO Cartridge, enhanced by a flat gold mouthpiece for smooth delivery and refined aesthetics.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '372',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-evo-cartridge-fluted-gold-mouthpiece',
        'title' => 'CCELL TH210 EVO Cartridge – Fluted Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'Savor a premium vaping experience with this <strong>TH2 EVO</strong> cartridge, featuring a <strong>1 mL glass reservoir</strong>, elegantly <strong>fluted gold</strong> mouthpiece, and advanced <strong>EVO ceramic</strong> coil technology. Its <strong>threaded design</strong> offers quick, leak-resistant refills, while <strong>standard 510</strong> compatibility ensures effortless pairing with most vape batteries.
<h3>Key Features</h3>
<ul>
 	<li><strong>Fluted Gold Mouthpiece:</strong> Ergonomic shape with a luxurious finish</li>
 	<li><strong>EVO Ceramic Coil:</strong> Provides smooth, even heating for full-flavored vapor</li>
 	<li><strong>Glass Tank (1 mL):</strong> Preserves the purity and aroma of your oils</li>
 	<li><strong>Threaded Closure:</strong> Simple twist-off design for easy refills</li>
 	<li><strong>Universal 510 Thread:</strong> Compatible with a wide range of battery mods</li>
</ul>
<h3>Why You’ll Love It</h3>
<ul>
 	<li>Eye-catching style meets everyday durability</li>
 	<li>Larger capacity for fewer refills on the go</li>
 	<li>Consistent, robust hits thanks to the EVO ceramic core</li>
 	<li>Leak-resistant design for peace of mind</li>
</ul>
Elevate your routine with the <strong>CCELL TH2 EVO Cartridge – Fluted Gold Mouthpiece (1 mL)</strong> and enjoy a perfect blend of performance, convenience, and style.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH210EVO‐GRF',
            '_regular_price' => '4.95',
            '_price' => '4.95',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.42',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.42',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.95',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.50',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.60',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.88',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.42',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.88',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.42',
            'wholesale_customer_wholesale_price' => '2.88',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.88',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.42',
  ),
),
            '_yoast_wpseo_metadesc' => 'Take precision to the next level with the CCELL TH210 EVO Cartridge, featuring a beautifully designed fluted gold mouthpiece for an optimal vaping experience.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '620',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th205-evo-cartridge-fluted-gold-mouthpiece',
        'title' => 'CCELL TH205 EVO Cartridge – Fluted Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'Experience the perfect blend of style and performance with this <strong>TH2 EVO</strong> cartridge, featuring a <strong>fluted gold mouthpiece</strong> and <strong>0.5 mL</strong> glass reservoir. The <strong>EVO ceramic</strong> coil delivers smooth, full-flavored vapor, while the <strong>threaded</strong> design ensures easy, leak-resistant refills. <strong>510</strong> compatibility lets you pair it effortlessly with most vape batteries.
<h3>Key Features</h3>
<ul>
 	<li><strong>Fluted Gold Mouthpiece:</strong> Eye-catching design with ergonomic comfort</li>
 	<li><strong>EVO Ceramic Coil:</strong> Consistent heating for rich, pure flavor</li>
 	<li><strong>Glass Tank (0.5 mL):</strong> Preserves the quality and aroma of your oils</li>
 	<li><strong>Threaded Seal:</strong> Twist-off top for simple refilling</li>
 	<li><strong>Standard 510 Thread:</strong> Works with a wide range of battery mods</li>
</ul>
<h3>Why You’ll Love It</h3>
<ul>
 	<li>Compact capacity for portable convenience</li>
</ul>
<ul>
 	<li>Durable, leak-resistant design for peace of mind</li>
 	<li>Superior coil technology for smoother draws</li>
</ul>
Upgrade your sessions with the <strong>CCELL TH2 EVO Cartridge – Fluted Gold Mouthpiece (0.5 mL)</strong> and enjoy a refined vaping experience every time.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH205EVO‐GRF',
            '_regular_price' => '4.79',
            '_price' => '4.79',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.79',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$3.72',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$2.92',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.62',
            '_yoast_wpseo_metadesc' => 'Engineered for performance, the CCELL TH205 EVO Cartridge with its fluted gold mouthpiece offers consistent vapor production with a luxurious feel.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '679',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-evo-cartridge-duckbill-gold-mouthpiece',
        'title' => 'CCELL TH210 EVO Cartridge – Duckbill Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'Built on the EVO platform, this TH210 features a <strong>duckbill gold mouthpiece</strong> for a natural lip seal and steady airflow. EVO’s next‑gen <strong>ceramic heating</strong> delivers clean, consistent flavor through a <strong>glass tank</strong>, while <strong>510</strong> compatibility and a <strong>threaded</strong> top keep everyday use simple and mess‑free. <em>(Copy follows the EVO styling used on your flat listings; capacity callouts omitted to stay consistent across variants.)</em>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_regular_price' => '4.95',
            '_price' => '4.95',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.42',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.42',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.95',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.50',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.60',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$2.88',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.42',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.88',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$2.42',
            'wholesale_customer_wholesale_price' => '2.88',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.88',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.42',
  ),
),
            '_yoast_wpseo_metadesc' => 'Upgrade your sessions using the CCELL TH210 EVO Cartridge, meticulously crafted with a sleek flat gold mouthpiece for optimal flavor delivery.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th205-evo-cartridge-duckbill-gold-mouthpiece',
        'title' => 'CCELL TH205 EVO Cartridge – Duckbill Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'Compact and advanced: the TH205 EVO combines a <strong>duckbill gold mouthpiece</strong> with EVO’s <strong>tri‑level ceramic heating</strong> for clean, even vapor. The <strong>glass reservoir</strong>, <strong>510</strong> compatibility, and <strong>threaded</strong> refill design make daily use simple; the polished gold finish keeps it looking premium.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH205EVO‐GF-1',
            '_regular_price' => '4.79',
            '_price' => '4.79',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.79',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$3.72',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$2.92',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.05',
            '_yoast_wpseo_metadesc' => 'Indulge in top-tier design with the CCELL TH205 EVO Cartridge, enhanced by a duckbill gold mouthpiece for smooth delivery and refined aesthetics.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'th210y-evo-1-0ml-cart-with-white-ceramic-mouthpiece',
        'title' => 'CCELL® TH210Y 1.0ml Cartridge (“Press-On” White Ceramic Mouthpiece)',
        'content' => '',
        'excerpt' => '<strong>All-New Heating Technology</strong>

<strong> </strong>Meet CCELL EVO, an enhanced and optimized heating technology expertly crafted to provide a premium sensory experience that will satisfy your cravings for authentic flavors, larger clouds, and maximum consistency.

<strong> </strong><strong>TH2-EVO Specifications:</strong>
<ul>
 	<li>Medical-grade Stainless Steel Center Post</li>
 	<li>Borosilicate Glass Body</li>
 	<li>Tank Volume: 1.0ml/0.5ml</li>
 	<li>Resistance: 1.4Ω</li>
 	<li>Press-On Mouthpiece</li>
 	<li>Connector: 510 Thread</li>
 	<li>Dimension:
<ul>
 	<li>1.0ml: 10.5(D) x 62(H)mm</li>
 	<li>0.5ml: 10.5(D) x 52(H)mm</li>
</ul>
</li>
</ul>
<ul>
 	<li>Aperture Inlet: 4 x Ф2mm</li>
 	<li>Various Mouthpieces Are Available, Please Contact Us for Details.</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH210Y-EVO-WCM',
            '_regular_price' => '4.79',
            '_price' => '4.79',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.79',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$3.72',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$2.92',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.62',
            '_yoast_wpseo_metadesc' => 'Elevate your vaping experience with the TH210Y EVO 1.0ml Cart featuring a sleek and stylish white ceramic mouthpiece. Discover the perfect blend of aesthetics and performance today!',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '16865',
            '_wc_average_rating' => '4.50',
            '_wc_review_count' => '2',
        ),
    ),
    array(
        'slug' => 'th210y-evo-1-0ml-cart-with-black-ceramic-mouthpiece',
        'title' => 'CCELL® TH210Y 1.0ml Cartridge (“Press-On” Black Ceramic Mouthpiece)',
        'content' => '',
        'excerpt' => '<strong>All-New Heating Technology</strong>

<strong> </strong>Meet CCELL EVO, an enhanced and optimized heating technology expertly crafted to provide a premium sensory experience that will satisfy your cravings for authentic flavors, larger clouds, and maximum consistency.

<strong> </strong><strong>TH2-EVO Specifications:</strong>
<ul>
 	<li>Medical-grade Stainless Steel Center Post</li>
 	<li>Borosilicate Glass Body</li>
 	<li>Tank Volume: 1.0ml/0.5ml</li>
 	<li>Resistance: 1.4Ω</li>
 	<li>Press-On Mouthpiece</li>
 	<li>Connector: 510 Thread</li>
 	<li>Dimension:
<ul>
 	<li>1.0ml: 10.5(D) x 62(H)mm</li>
 	<li>0.5ml: 10.5(D) x 52(H)mm</li>
</ul>
</li>
</ul>
<ul>
 	<li>Aperture Inlet: 4 x Ф2mm</li>
 	<li>Various Mouthpieces Are Available, Please Contact Us for Details.</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH210Y-EVO-BCM',
            '_regular_price' => '4.79',
            '_price' => '4.79',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.79',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$3.72',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$2.92',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.62',
            '_yoast_wpseo_metadesc' => 'Experience premium quality with the TH210Y EVO 1.0ml Cart featuring a black ceramic mouthpiece. Enhance your vaping journey today!',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'th205y-evo-0-5ml-cart-with-white-ceramic-mouthpiece',
        'title' => 'CCELL® TH205-Y 0.5ml Cartridge (“Press-On” White Ceramic Mouthpiece)',
        'content' => '',
        'excerpt' => '<strong>All-New Heating Technology</strong>

<strong> </strong>Meet CCELL EVO, an enhanced and optimized heating technology expertly crafted to provide a premium sensory experience that will satisfy your cravings for authentic flavors, larger clouds, and maximum consistency.

<strong> </strong><strong>TH2-EVO Specifications:</strong>
<ul>
 	<li>Medical-grade Stainless Steel Center Post</li>
 	<li>Borosilicate Glass Body</li>
 	<li>Tank Volume: 1.0ml/0.5ml</li>
 	<li>Resistance: 1.4Ω</li>
 	<li>Press On Mouthpiece</li>
 	<li>Connector: 510 Thread</li>
 	<li>Dimension:
<ul>
 	<li>1.0ml: 10.5(D) x 62(H)mm</li>
 	<li>0.5ml: 10.5(D) x 52(H)mm</li>
</ul>
</li>
</ul>
<ul>
 	<li>Aperture Inlet: 4 x Ф2mm</li>
 	<li>Various Mouthpieces Are Available, Please Contact Us for Details.</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH205Y-EVO-WCM',
            '_regular_price' => '4.79',
            '_price' => '4.79',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.79',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$3.72',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$2.92',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.62',
            '_yoast_wpseo_metadesc' => 'Upgrade your vaping game with the TH205Y EVO 0.5ml cartridge featuring a white ceramic mouthpiece from Hamilton Devices.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '739',
            '_wc_average_rating' => '5.00',
            '_wc_review_count' => '1',
        ),
    ),
    array(
        'slug' => 'th205y-evo-0-5ml-cart-with-black-ceramic-mouthpiece',
        'title' => 'CCELL® TH205-Y 0.5ml Cartridge (“Press-On” Black Ceramic Mouthpiece)',
        'content' => '',
        'excerpt' => '<h3>Please contact us for availability at
<img class="size-full wp-image-185876" title="wholesale" src="https://hamiltondevices.com/wp-content/uploads/2023/01/wholesale.png" alt="wholesale" width="286" height="21" /></h3>
<strong>All-New Heating Technology</strong>

<strong> </strong>Meet CCELL EVO, an enhanced and optimized heating technology expertly crafted to provide a premium sensory experience that will satisfy your cravings for authentic flavors, larger clouds, and maximum consistency.

<strong> </strong><strong>TH2-EVO Specifications:</strong>
<ul>
 	<li>Medical-grade Stainless Steel Center Post</li>
 	<li>Borosilicate Glass Body</li>
 	<li>Tank Volume: 1.0ml/0.5ml</li>
 	<li>Resistance: 1.4Ω</li>
 	<li>Press-On Mouthpiece</li>
 	<li>Connector: 510 Thread</li>
 	<li>Dimension:
<ul>
 	<li>1.0ml: 10.5(D) x 62(H)mm</li>
 	<li>0.5ml: 10.5(D) x 52(H)mm</li>
</ul>
</li>
</ul>
<ul>
 	<li>Aperture Inlet: 4 x Ф2mm</li>
 	<li>Various Mouthpieces Are Available, Please Contact Us for Details.</li>
</ul>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH205Y-EVO-BCM',
            '_regular_price' => '4.79',
            '_price' => '4.79',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.79',
            'quantity_limit_2' => '20-50',
            'price_per_unit_2' => '$3.72',
            'quantity_limit_3' => '51-99',
            'price_per_unit_3' => '$2.92',
            'quantity_limit_4' => '100+',
            'price_per_unit_4' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.62',
            '_yoast_wpseo_metadesc' => 'Experience the ultimate in vaping satisfaction with the TH205Y EVO - shop now and enjoy a premium vaping experience like no other.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '10',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-s-cartridge-flat-gold-mouthpiece',
        'title' => 'CCELL TH210 S Cartridge – Flat Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'Experience a streamlined vape session with this <strong>1 mL TH2 S</strong> cartridge, complete with a <strong>flat gold mouthpiece</strong> and sturdy <strong>glass reservoir</strong>. Its <strong>threaded</strong> closure makes refills quick and leak‐resistant, while <strong>510</strong> compatibility pairs easily with a wide range of batteries.
<h3>Key Features</h3>
<ul>
 	<li><strong>Flat Gold Mouthpiece:</strong> Sleek, corrosion‐resistant design</li>
 	<li><strong>Glass Tank (1 mL):</strong> Preserves authentic flavor</li>
 	<li><strong>Threaded Seal:</strong> Twist‐on closure for effortless, secure refills</li>
 	<li><strong>Ceramic Coil:</strong> Delivers smooth, consistent vapor</li>
 	<li><strong>Universal 510 Thread:</strong> Broad compatibility with most battery mods</li>
</ul>
<h3>Why You’ll Love It</h3>
<ul>
 	<li>Larger tank capacity for fewer refills</li>
 	<li>Durable build that’s made for daily use</li>
 	<li>Easy to maintain and assemble—no tools needed</li>
</ul>
Elevate your vaping experience with the <strong>CCELL TH2 S Cartridge – Flat Gold Mouthpiece (1 mL)</strong>, where simplicity, style, and reliability converge.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH210S‐GF',
            '_regular_price' => '4.61',
            '_price' => '4.61',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.61',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.24',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.60',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$2.88',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.62',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.88',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.05',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.88',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.62',
  ),
),
            '_yoast_wpseo_metadesc' => 'Elevate your vaping experience with the CCELL TH210 S Cartridge featuring a premium flat gold mouthpiece designed for smooth and consistent performance.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '550',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th205-s-cartridge-flat-gold-mouthpiece',
        'title' => 'CCELL TH205 S Cartridge – Flat Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'Enjoy smooth, reliable performance in a compact 0.5 mL size. This <strong>TH2 S</strong> cartridge features a <strong>flat gold mouthpiece</strong> and a sturdy <strong>glass</strong> reservoir for pure flavor and easy monitoring of oil levels. Threaded for simple, leak-resistant refills, it’s fully compatible with <strong>510</strong> vape batteries.
<h3>Key Features</h3>
<ul>
 	<li><strong>Flat Gold Mouthpiece:</strong> Streamlined and corrosion-resistant</li>
 	<li><strong>Glass Tank (0.5 mL):</strong> Preserves taste and aroma</li>
 	<li><strong>Threaded Seal:</strong> Unscrew to refill, then twist securely back in place</li>
 	<li><strong>Ceramic Heating Core:</strong> Consistent, flavorful draws every time</li>
 	<li><strong>Standard 510 Thread:</strong> Universal fit for most vape devices</li>
</ul>
<h3>Why You’ll Love It</h3>
<ul>
 	<li>Elegant gold finish elevates your setup</li>
 	<li>Compact design is perfect for portable use</li>
 	<li>Easy, tool-free refills</li>
 	<li>Durable materials for everyday reliability</li>
</ul>
Upgrade your daily vape with the <strong>CCELL TH2 S Cartridge – Flat Gold Mouthpiece (0.5 mL)</strong>, where sleek design meets dependable, leak-resistant performance.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH205S‐GF',
            '_regular_price' => '3.66',
            '_price' => '3.66',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.70',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.70',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$3.66',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.93',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.34',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$1.87',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.70',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.87',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$1.70',
            'wholesale_customer_wholesale_price' => '1.87',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.87',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.70',
  ),
),
            '_yoast_wpseo_metadesc' => 'Define your vaping standards with the CCELL TH205 S Cartridge, designed with a durable flat gold mouthpiece for a consistently smooth draw.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '13',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-s-cartridge-fluted-gold-mouthpiece',
        'title' => 'CCELL TH210 S Cartridge – Fluted Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'Enjoy a refined vaping experience with this <strong>1 mL TH2 S</strong> cartridge, featuring a <strong>fluted gold mouthpiece</strong> and sturdy <strong>glass reservoir</strong>. Its <strong>threaded</strong> design simplifies refills while the universal <strong>510</strong> thread ensures broad device compatibility.
<h3>Key Features</h3>
<ul>
 	<li><strong>Fluted Gold Mouthpiece:</strong> Ergonomic shape with an elegant look</li>
 	<li><strong>Glass Tank (1 mL):</strong> Maintains authentic flavors and aromas</li>
 	<li><strong>Threaded Seal:</strong> Twist to open/close for reliable, leak-resistant refills</li>
 	<li><strong>Ceramic Coil:</strong> Consistent heating for smooth, flavorful draws</li>
 	<li><strong>510 Thread:</strong> Pairs with most standard vape batteries</li>
</ul>
<h3>Why You’ll Love It</h3>
<ul>
 	<li>Ample 1 mL capacity means fewer refills</li>
 	<li>Durable materials for everyday use</li>
 	<li>Tool-free setup and secure sealing</li>
</ul>
Elevate your daily vape sessions with the <strong>CCELL TH2 S Cartridge – Fluted Gold Mouthpiece (1 mL)</strong>—premium design meets dependable performance.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH210S‐GRF',
            '_regular_price' => '4.61',
            '_price' => '4.61',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.61',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.24',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.60',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$2.88',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.62',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.88',
            'wquantity_limit_2' => '2,000+',
            'wprice_per_unit_2' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.88',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.88',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.62',
  ),
),
            '_yoast_wpseo_metadesc' => 'Step into superior quality with the CCELL TH210 S Cartridge, equipped with a stylish fluted gold mouthpiece to ensure a reliable and smooth draw.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '7',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th205-s-cartridge-fluted-gold-mouthpiece',
        'title' => 'CCELL TH205 S Cartridge – Fluted Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'Experience a sleek and convenient vape setup with this <strong>0.5 mL TH2 S</strong> cartridge, featuring a <strong>fluted gold mouthpiece</strong> and a robust <strong>glass reservoir</strong>. Its <strong>threaded</strong> design allows for simple, leak-resistant refills, and the <strong>510</strong> thread ensures broad compatibility with most devices.
<h3>Key Features</h3>
<ul>
 	<li><strong>Fluted Gold Mouthpiece:</strong> Ergonomic, elegant design</li>
 	<li><strong>Glass Tank (0.5 mL):</strong> Preserves genuine flavor</li>
 	<li><strong>Threaded Closure:</strong> Twist on/off for quick, secure refills</li>
 	<li><strong>Ceramic Coil:</strong> Consistent heat distribution for smooth draws</li>
 	<li><strong>510 Thread:</strong> Works seamlessly with popular battery mods</li>
</ul>
<h3>Why You’ll Love It</h3>
<ul>
 	<li>Portable size suited to everyday use</li>
 	<li>Premium materials ensure durability</li>
 	<li>Easy, tool-free refilling</li>
</ul>
Enhance your daily vape routine with the <strong>CCELL TH2 S Cartridge – Fluted Gold Mouthpiece (0.5 mL)</strong>, where comfort, style, and reliability come together.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH205S‐GRF',
            '_regular_price' => '3.66',
            '_price' => '3.66',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.70',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.70',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$3.66',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.93',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.34',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$1.87',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.70',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.87',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$1.70',
            'wholesale_customer_wholesale_price' => '1.87',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.87',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.70',
  ),
),
            '_yoast_wpseo_metadesc' => 'Stand out with the TH205 S Cartridge featuring a luxurious fluted gold mouthpiece and signature CCELL tech for smooth, reliable draws every time.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '225',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th210-s-cartridge-duckbill-gold-mouthpiece',
        'title' => 'CCELL TH210 S Cartridge – Duckbill Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'Experience a streamlined session with the TH210 S fitted with a <strong>duckbill gold mouthpiece</strong>. The wider, ergonomic profile seals naturally for a smooth, stable draw, while the polished metal finish adds a premium look. Built on CCELL’s proven platform with a clean <strong>glass tank</strong>, <strong>ceramic heating</strong>, and <strong>universal 510</strong> compatibility—plus a <strong>threaded</strong> top for quick, leak‑resistant refills.',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_regular_price' => '4.61',
            '_price' => '4.61',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '2.62',
            'wprice_text' => 'As low as',
            'wlowest_price' => '2.62',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$4.61',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$4.24',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$3.60',
            'quantity_limit_4' => '100–1,999',
            'price_per_unit_4' => '$2.88',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$2.62',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$2.88',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$2.62',
            'wholesale_customer_wholesale_price' => '2.05',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.88',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '2.62',
  ),
),
            '_yoast_wpseo_metadesc' => 'Elevate your vaping experience with the CCELL TH210 S Cartridge featuring a premium duckbill gold mouthpiece designed for smooth and consistent performance.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-th205-s-cartridge-duckbill-gold-mouthpiece',
        'title' => 'CCELL TH205 S Cartridge – Duckbill Gold Mouthpiece',
        'content' => '',
        'excerpt' => 'A compact workhorse with an <strong>ergonomic duckbill gold mouthpiece</strong> that feels great and looks even better. The polished metal finish complements the clear <strong>glass tank</strong>, while CCELL’s <strong>ceramic heating</strong> and <strong>universal 510</strong> keep performance and compatibility on point. Secure top closure design makes refills straightforward and reliable. <em>(Language mirrors the TH210 S/SE flat pages while keeping closure phrasing model‑agnostic.)</em>',
        'categories' => array (
  0 => 'classic',
),
        'meta' => array(
            '_sku' => 'TH205S‐GF-1',
            '_regular_price' => '3.66',
            '_price' => '3.66',
            '_stock_status' => 'notify_me',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            'price_text' => 'As low as',
            'lowest_price' => '1.70',
            'wprice_text' => 'As low as',
            'wlowest_price' => '1.70',
            'table_name' => 'Bulk Pricing',
            '1st_column_name' => 'Quantity',
            '2nd_column_name' => 'Price per unit',
            'quantity_limit_1' => '1-19 ',
            'price_per_unit_1' => '$3.66',
            'quantity_limit_2' => '20-49',
            'price_per_unit_2' => '$2.93',
            'quantity_limit_3' => '50-99',
            'price_per_unit_3' => '$2.34',
            'quantity_limit_4' => '100-1,999',
            'price_per_unit_4' => '$1.87',
            'quantity_limit_5' => '2,000+',
            'price_per_unit_5' => '$1.70',
            'wtable_name' => 'Wholesale Pricing',
            'wquantity_limit_1' => '100–1,999',
            'wprice_per_unit_1' => '$1.87',
            'wquantity_limit_2' => '2,000+ ',
            'wprice_per_unit_2' => '$1.70',
            'wholesale_customer_wholesale_price' => '1.87',
            'wwpp_post_meta_quantity_discount_rule_mapping' => array (
  0 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '100',
    'end_qty' => '1999',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.87',
  ),
  1 => 
  array (
    'wholesale_role' => 'wholesale_customer',
    'start_qty' => '2000',
    'end_qty' => '',
    'price_type' => 'fixed-price',
    'wholesale_price' => '1.70',
  ),
),
            '_yoast_wpseo_metadesc' => 'Define your vaping standards with the CCELL TH205 S Cartridge, designed with a durable duckbill gold mouthpiece for a consistently smooth draw.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-palm-se-classic-fit-with-unobstructed-visibility',
        'title' => 'CCELL® Palm SE – Classic Fit with Unobstructed Visibility',
        'content' => '',
        'excerpt' => 'The CCELL Palm SE is a compact, palm-fitting 510-thread battery featuring a classic design with an unobstructed oil view window for easy monitoring.

It delivers consistent 15-second stable temperature heating for smooth, flavorful vapor, with three variable voltage settings for customized experiences. Inhale-activated and USB-C rechargeable, it\'s powered by a 500mAh battery for extended use, making it ideal for beginners and experts seeking reliability.
<h4><strong>Key Features</strong></h4>
<ul>
 	<li>Classic palm-fitting battery design for comfortable, discreet use.</li>
 	<li>Unobstructed oil view window for clear visibility of cartridge levels.</li>
 	<li>15-second stable temperature heating for consistent, burn-free vapor.</li>
 	<li>Three variable voltage settings (2.8V for flavor, 3.2V for balance, 3.6V for clouds).</li>
 	<li>Inhale-activated firing for effortless operation.</li>
 	<li>Standard 510 thread compatibility with most cartridges.</li>
 	<li>Type-C charging for fast, convenient recharging.</li>
</ul>
<h4><strong>Specifications</strong></h4>
<ul>
 	<li>Battery Capacity: 500mAh</li>
 	<li>Dimensions: 57.6mm H x 41.9mm W x 13.5mm D (2.27in H x 1.65in W x 0.53in D)</li>
 	<li>Voltage Settings: 2.8V, 3.2V, 3.6V</li>
 	<li>Heating: 15-second stable temperature</li>
 	<li>Activation: Inhale-activated</li>
 	<li>Charging: USB-C</li>
 	<li>Thread: Standard 510</li>
 	<li>Colors Available: Silver, Gold</li>
</ul>',
        'categories' => array (
  0 => 'ccell',
),
        'meta' => array(
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            '_yoast_wpseo_metadesc' => 'Styled for simplicity, the CCELL® Palm SE keeps your oil visible and your draw consistent with a compact, timeless design.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
    array(
        'slug' => 'ccell-m4b-pro-customizable-voltage-with-secure-power-lock',
        'title' => 'CCELL® M4B Pro – Customizable Voltage with Secure Power Lock',
        'content' => '',
        'excerpt' => 'The CCELL M4B Pro is a reliable, high-performance 510-thread battery with a 290mAh capacity, designed for seamless compatibility with standard cartridges.

It features inhale-activated firing, a 10-second preheat for optimal flavor, protective power lock for safety, and three adjustable voltage settings for personalized vaping experiences. With effortless USB-C charging and a durable stainless steel housing.
<h4><strong>Key Features</strong></h4>
<ul>
 	<li>10-second preheat to gently warm oil for full flavor and rich vapor (tap button twice).</li>
 	<li>Protective power lock to prevent accidental activation and unauthorized use (tap button five times to turn on/off).</li>
 	<li>Three voltage settings (2.8V for true-to-plant taste, 3.2V for balanced experience, 3.6V for large clouds – tap button three times to adjust).</li>
 	<li>Inhale-activated firing with LED function indicator for effortless operation.</li>
 	<li>Effortless USB-C charging compatible with any cable.</li>
 	<li>Durable stainless steel housing for longevity.</li>
</ul>
<h4><strong>Specifications</strong></h4>
<ul>
 	<li>Battery Capacity: 290mAh</li>
 	<li>Dimensions: 10.5mm W x 90.75mm H (0.41in W x 3.57in H)</li>
 	<li>Voltage Settings: 2.8V, 3.2V, 3.6V</li>
 	<li>Preheat: 10 seconds</li>
 	<li>Activation: Inhale-activated with button controls</li>
 	<li>Charging: USB-C</li>
 	<li>Thread: Standard 510</li>
 	<li>Housing: Stainless steel</li>
 	<li>Colors Available: Black, White, Silver</li>
</ul>',
        'categories' => array (
  0 => 'ccell',
),
        'meta' => array(
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_tax_status' => 'taxable',
            '_sold_individually' => 'no',
            '_backorders' => 'no',
            '_virtual' => 'no',
            '_yoast_wpseo_metadesc' => 'Designed for control, the CCELL® M4B Pro lets you fine-tune voltage with a secure power lock for consistent performance and safe vaping.',
            'wc_productdata_options' => array (
  0 => 
  array (
    '_product_block' => '0',
    '_top_content' => '',
    '_bottom_content' => '',
    '_bubble_new' => '',
    '_bubble_text' => '',
    '_custom_tab_title' => '',
    '_custom_tab' => '',
    '_product_video' => '',
    '_product_video_size' => '',
    '_product_video_placement' => '',
  ),
),
            'total_sales' => '0',
            '_wc_average_rating' => '0',
            '_wc_review_count' => '0',
        ),
    ),
);

foreach ($existing_product_updates as $upd) {
    $post = get_page_by_path($upd["slug"], OBJECT, "product");
    if (!$post) {
        mlog("  SKIP (not found): {$upd['slug']}");
        continue;
    }
    
    // Replace Docker image IDs in content
    $content = hd_replace_image_ids($upd["content"], $docker_to_prod_id);
    
    // Update post content and excerpt
    wp_update_post(array(
        "ID" => $post->ID,
        "post_title" => $upd["title"],
        "post_content" => $content,
        "post_excerpt" => $upd["excerpt"],
    ));
    
    // Update meta
    foreach ($upd["meta"] as $key => $val) {
        update_post_meta($post->ID, $key, $val);
    }
    
    // Set categories (replace, not append)
    if (!empty($upd["categories"])) {
        wp_set_object_terms($post->ID, $upd["categories"], "product_cat");
    }
    
    mlog("  UPDATED: {$upd['slug']} (ID: {$post->ID})");
}
mlog("");


// ============================================================
// SECTION 6: Archive Legacy Products
// ============================================================
mlog("--- Section 6: Archive Legacy Products ---");

$archived_product_slugs = array (
  0 => 'jetstream-m3-plus-combo',
  1 => 'm6t-0-5-se-cartridge-m3-battery-combo',
  2 => 'jetstream-concentrate-kit-combo',
  3 => 'ccell-poche-0-5ml-disposable',
  4 => 'jetstream-mini',
  5 => 'ccell-ds0110-us-1-0ml-disposable-with-threaded-sandalwood-round-mouthpiece',
  6 => 'ccell-klean-white-with-white-round-mouthpiece',
  7 => 'ccell-klean-stainless-steel-with-clear-round-mouthpiece',
  8 => 'klean',
  9 => 'ccell-sandwave',
  10 => 'jetstream',
  11 => 'butterfly',
  12 => 'ccell-eazie-0-3ml-disposable',
  13 => 'ccell-sima-1-0ml-disposable',
  14 => 'owa-0-5ml-disposable',
  15 => 'skye-ii-1-0ml-disposable',
  16 => 'm3-plus',
  17 => 'rizo',
  18 => 'luster-pod-0-5ml-with-cr-package',
  19 => 'ccell-zico-1-0ml-bottom-fill-cartridge',
  20 => 'ccell-zico-0-5ml-bottom-fill-cartridge',
  21 => 'nomad',
  22 => 'ccell-palm-battery-red-with-blue-frame',
  23 => 'ccell-palm-battery-rose-gold-with-pink',
  24 => 'ccell-palm-battery-black-with-yellow-frame',
  25 => 'ccell-palm-battery-gray-with-orange-frame',
  26 => 'starship',
  27 => 'gamer-battery',
  28 => 'ccell-slym-0-3ml-disposable',
  29 => 'ccell-memento-0-3ml-disposable',
  30 => 'ccell-ds1903-u-0-3ml-rechargeable-disposable-pod-vaporizer',
  31 => 'the-shiv2',
  32 => 'ccell-palm-battery-yellow-with-purple-frame',
  33 => 'ccell-palm-battery-purple-with-gold-frame',
  34 => 'ccell-palm-battery-green-with-rose-gold-frame',
  35 => 'ccell-palm-battery-blue',
  36 => 'ccell-ds1903-m-0-3ml-disposable-pod-vaporizer',
  37 => 'skye-ii-0-5ml-disposable',
  38 => 'ccell-th001-disposable-vaporizer-pen',
  39 => 'ccell-ds0110-us-1-0ml-disposable-with-threaded-stainless-steel-mouthpiece',
  40 => 'ccell-ds0110-us-1-0ml-disposable-with-threaded-sandalwood-flat-mouthpiece',
  41 => 'ccell-ds0110-us-1-0ml-disposable-with-threaded-chrome-round-fluted-mouthpiece',
  42 => 'ccell-ds0110-us-1-0ml-disposable-with-threaded-chrome-flat-mouthpiece',
  43 => 'ccell-ds0110-us-1-0ml-disposable-with-threaded-white-ceramic-mouthpiece',
  44 => 'ccell-ds0110-us-1-0ml-disposable-with-threaded-black-ceramic-mouthpiece',
  45 => 'ccell-ds0110-us-1-0ml-disposable-with-threaded-white-plastic-flat-mouthpiece',
  46 => 'ccell-ds0110-us-1-0ml-disposable-with-threaded-black-plastic-flat-mouthpiece',
  47 => 'ccell-ds01-series-disposable',
  48 => 'pb1',
  49 => 'kr1',
  50 => 'ps1',
  51 => 'uno-battery',
  52 => 'cube-battery',
  53 => 'uno-pods',
  54 => 'gold-bar',
  55 => 'cloak-battery',
  56 => 'tombstone-battery-matte-black',
  57 => 'luster',
  58 => 'ccell-silo-battery-gold-electroplated',
  59 => 'ccell-silo-battery-red-anodized',
  60 => 'ccell-silo-battery-black',
  61 => 'ccell-silo-battery-gray',
  62 => 'ccell-silo-battery-blue',
  63 => 'ccell-silo-battery-pink',
  64 => 'ccell-510-thread-vaporizer-pen-battery510-thread-vaporizer-pen-battery',
  65 => 'ccell-510-thread-vaporizer-pen-battery-stainless-steel',
  66 => 'ccell-510-thread-vaporizer-pen-battery-gold-electroplated',
  67 => 'ccell-510-thread-vaporizer-pen-battery-rose-gold',
  68 => 'ccell-510-thread-vaporizer-pen-battery-rainbow',
  69 => 'm3b',
  70 => 'ccell-palm-pack-blue-1-palm-battery-with-charger-2-of-05ml-ccell-silver-glass-vaporizer-cartridge-with-threaded-white-ceramic',
  71 => 'five-pack-5-of-05ml-ccell-silver-glass-vaporizer-cartridge-with-threaded-white-ceramic-mouthpiece',
  72 => 'ten-pack-10-of-05ml-ccell-silver-glass-vaporizer-cartridge-with-threaded-white-ceramic-mouthpiece',
);

$archived_count = 0;
foreach ($archived_product_slugs as $slug) {
    $post = get_page_by_path($slug, OBJECT, "product");
    if (!$post) {
        mlog("  SKIP (not found): $slug");
        continue;
    }
    
    // Set visibility to hidden
    update_post_meta($post->ID, "_visibility", "hidden");
    
    // Ensure in ccell-classics category
    $current_cats = wp_get_object_terms($post->ID, "product_cat", array("fields" => "slugs"));
    if (!in_array("ccell-classics", $current_cats)) {
        wp_set_object_terms($post->ID, "ccell-classics", "product_cat", true);
    }
    
    $archived_count++;
}
mlog("  Archived $archived_count products");
mlog("");


// ============================================================
// SECTION 7: Menu Modifications
// ============================================================
mlog("--- Section 7: Menu Modifications ---");

// Find the primary menu
$menu_locations = get_nav_menu_locations();
$primary_menu_id = 0;

// Try to find by name first
$main_menu = wp_get_nav_menu_object("Main");
if ($main_menu) {
    $primary_menu_id = $main_menu->term_id;
} elseif (isset($menu_locations["primary"])) {
    $primary_menu_id = $menu_locations["primary"];
}

if (!$primary_menu_id) {
    mlog("  WARNING: Could not find primary menu. Skipping menu modifications.");
} else {
    $menu_items = wp_get_nav_menu_items($primary_menu_id);
    
    // Find items by title for modification
    $deals_item = null;
    $reviews_item = null;
    $shop_item = null;
    
    foreach ($menu_items as $item) {
        if ($item->title === "Deals" && $item->menu_item_parent == 0) $deals_item = $item;
        if ($item->title === "Reviews" && $item->menu_item_parent == 0) $reviews_item = $item;
        if ($item->title === "Shop" && $item->menu_item_parent == 0) $shop_item = $item;
    }
    
    // Rename "Shop" to "Products"
    if ($shop_item) {
        wp_update_nav_menu_item($primary_menu_id, $shop_item->db_id, array(
            "menu-item-title" => "Products",
            "menu-item-url" => $shop_item->url,
            "menu-item-status" => "publish",
            "menu-item-type" => $shop_item->type,
            "menu-item-object" => $shop_item->object,
            "menu-item-object-id" => $shop_item->object_id,
        ));
        mlog("  Renamed Shop to Products");
    }
    
    // Set Deals and Reviews to draft (hide them)
    if ($deals_item) {
        wp_update_post(array("ID" => $deals_item->db_id, "post_status" => "draft"));
        mlog("  Hidden: Deals menu item");
    }
    if ($reviews_item) {
        wp_update_post(array("ID" => $reviews_item->db_id, "post_status" => "draft"));
        mlog("  Hidden: Reviews menu item");
    }
    
    // Check if Technology menu item already exists
    $tech_exists = false;
    $custom_branding_exists = false;
    $samples_exists = false;
    foreach ($menu_items as $item) {
        if ($item->title === "Technology" && $item->menu_item_parent == 0) $tech_exists = true;
        if ($item->title === "Custom Branding" && $item->menu_item_parent == 0) $custom_branding_exists = true;
        if ($item->title === "Request Samples" && $item->menu_item_parent == 0) $samples_exists = true;
    }
    
    // Add Technology dropdown
    if (!$tech_exists) {
        $tech_page = get_page_by_path("ccell-heating-technology");
        if ($tech_page) {
            $tech_menu_id = wp_update_nav_menu_item($primary_menu_id, 0, array(
                "menu-item-title" => "Technology",
                "menu-item-object" => "page",
                "menu-item-object-id" => $tech_page->ID,
                "menu-item-type" => "post_type",
                "menu-item-status" => "publish",
                "menu-item-position" => 5,
            ));
            
            if (!is_wp_error($tech_menu_id)) {
                // Add sub-items
                $tech_subs = array(
                    array("title" => "SE", "url" => "/ccell-heating-technology/#tech-se"),
                    array("title" => "EVO", "url" => "/ccell-heating-technology/#tech-evo"),
                    array("title" => "EVO MAX", "url" => "/ccell-heating-technology/#tech-evo-max"),
                    array("title" => "CCELL 3.0", "url" => "/ccell-heating-technology/#tech-3"),
                    array("title" => "HeRo", "url" => "/ccell-heating-technology/#tech-hero"),
                    array("title" => "Compare All", "url" => "/ccell-heating-technology/#comparison"),
                );
                
                foreach ($tech_subs as $sub) {
                    wp_update_nav_menu_item($primary_menu_id, 0, array(
                        "menu-item-title" => $sub["title"],
                        "menu-item-url" => $sub["url"],
                        "menu-item-type" => "custom",
                        "menu-item-status" => "publish",
                        "menu-item-parent-id" => $tech_menu_id,
                    ));
                }
                
                // Add Snap-Fit Capping sub-item
                $snapfit_page = get_page_by_path("technology/snap-fit-capping");
                if (!$snapfit_page) $snapfit_page = get_page_by_path("snap-fit-capping");
                if ($snapfit_page) {
                    wp_update_nav_menu_item($primary_menu_id, 0, array(
                        "menu-item-title" => "Snap-Fit Capping",
                        "menu-item-object" => "page",
                        "menu-item-object-id" => $snapfit_page->ID,
                        "menu-item-type" => "post_type",
                        "menu-item-status" => "publish",
                        "menu-item-parent-id" => $tech_menu_id,
                    ));
                }
                
                mlog("  CREATED: Technology dropdown with sub-items");
            }
        }
    } else {
        mlog("  SKIP: Technology menu item already exists");
    }
    
    // Add Custom Branding
    if (!$custom_branding_exists) {
        $cb_page = get_page_by_path("custom-branding");
        if ($cb_page) {
            wp_update_nav_menu_item($primary_menu_id, 0, array(
                "menu-item-title" => "Custom Branding",
                "menu-item-object" => "page",
                "menu-item-object-id" => $cb_page->ID,
                "menu-item-type" => "post_type",
                "menu-item-status" => "publish",
            ));
            mlog("  CREATED: Custom Branding menu item");
        }
    }
    
    // Add Request Samples
    if (!$samples_exists) {
        $rs_page = get_page_by_path("request-samples");
        if ($rs_page) {
            wp_update_nav_menu_item($primary_menu_id, 0, array(
                "menu-item-title" => "Request Samples",
                "menu-item-object" => "page",
                "menu-item-object-id" => $rs_page->ID,
                "menu-item-type" => "post_type",
                "menu-item-status" => "publish",
            ));
            mlog("  CREATED: Request Samples menu item");
        }
    }
}
mlog("");


// ============================================================
// SECTION 8: Homepage Content
// ============================================================
mlog("--- Section 8: Homepage Content ---");

$homepage_content = '
[ux_slider infinitive="false" mobile="false" bullet_style="dashes-spaced" timer="4000" pause_hover="false" class="sads" visibility="hide-for-small"]

[ux_banner height="42%" bg="227345" bg_size="original" link="https://hamiltondevices.com/product-category/ccell/"]


[/ux_banner]
[ux_banner height="42%" bg="194528" bg_size="original" link="https://hamiltondevices.com/product-category/cartridge/"]


[/ux_banner]
[ux_banner height="42%" bg="230236" bg_size="original" link="https://hamiltondevices.com/product/ccell-tank/"]


[/ux_banner]
[ux_banner height="42%" bg="172586" bg_size="original" link="https://hamiltondevices.com/product/ccell-listo-1-0ml-disposable/"]


[/ux_banner]

[/ux_slider]
[ux_slider style="container" hide_nav="true" nav_pos="outside" timer="4000" pause_hover="false" class="sads" visibility="show-for-small"]

[ux_banner height="300px" height__sm="59%" bg="227346" bg_size="original" bg_overlay="rgba(0, 0, 0, 0.05)" bg_overlay__sm="rgba(0, 0, 0, 0)" link="https://hamiltondevices.com/product-category/ccell/"]


[/ux_banner]
[ux_banner height="300px" height__sm="59%" bg="194529" bg_size="original" bg_overlay="rgba(0, 0, 0, 0.05)" bg_overlay__sm="rgba(0, 0, 0, 0)" bg_pos="0" link="https://hamiltondevices.com/product-category/cartridge/"]


[/ux_banner]
[ux_banner height="300px" height__sm="59%" bg="230237" bg_size="original" link="https://hamiltondevices.com/product/ccell-tank/"]


[/ux_banner]
[ux_banner height="300px" height__sm="59%" bg="171231" bg_size="original" bg_overlay="rgba(0, 0, 0, 0.05)" bg_overlay__sm="rgba(0, 0, 0, 0)" bg_pos="90%" link="https://hamiltondevices.com/product/ccell-listo-1-0ml-disposable/"]


[/ux_banner]

[/ux_slider]

[section bg_color="rgb(0,0,0)" padding="50px"]
[row h_align="center"]
[col span="12" span__sm="12" align="center"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<p style="text-transform:uppercase;letter-spacing:5px;color:#E50914;font-weight:700;font-size:12px;margin-bottom:30px;">Why Brands Choose Hamilton Devices</p>
[/ux_text]
[/col]
[/row]
[row h_align="center"]
[col span="2" span__sm="6" align="center" padding="15px 10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:25px 15px;">
<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">2016</span>
<div style="width:30px;height:2px;background:#E50914;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">CCELL® Partners<br>Since Day One</p>
</div>
[/ux_text]
[/col]
[col span="2" span__sm="6" align="center" padding="15px 10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:25px 15px;">
<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">5,000+</span>
<div style="width:30px;height:2px;background:#E50914;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">Wholesale<br>Customers Served</p>
</div>
[/ux_text]
[/col]
[col span="2" span__sm="6" align="center" padding="15px 10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:25px 15px;">
<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">Fast</span>
<div style="width:30px;height:2px;background:#E50914;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">Same-Day Processing<br>for U.S. Inventory</p>
</div>
[/ux_text]
[/col]
[col span="2" span__sm="6" align="center" padding="15px 10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:25px 15px;">
<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">Full</span>
<div style="width:30px;height:2px;background:#E50914;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">Custom Branding<br>&amp; White Label</p>
</div>
[/ux_text]
[/col]
[col span="2" span__sm="6" align="center" padding="15px 10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:25px 15px;">
<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">Global</span>
<div style="width:30px;height:2px;background:#E50914;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">Shipping &amp;<br>Logistics</p>
</div>
[/ux_text]
[/col]
[/row]
[/section]

[section padding="60px"]
[row]
[col span__sm="12" align="center"]
[ux_text font_size="1.5" text_align="center"]
<img src="/wp-content/uploads/2025/12/ccellnewlogo.png" alt="CCELL" style="max-width:160px;height:auto;margin-bottom:10px;">
<h2>Product Lines</h2>
[/ux_text]
[ux_text text_align="center"]
<p style="font-size:17px;max-width:650px;margin:0 auto 35px;color:#555;">The complete CCELL® hardware lineup, available for wholesale purchase with custom branding options on every product.</p>
[/ux_text]
[/col]
[/row]

<style>
.product-line-card {
  background:#fff;
  border-radius:12px;
  overflow:hidden;
  text-align:center;
  box-shadow:0 2px 20px rgba(0,0,0,0.06);
  transition:all 0.3s ease;
  border:1px solid rgba(0,0,0,0.04);
}
.product-line-card:hover {
  box-shadow:0 8px 40px rgba(0,0,0,0.12);
  transform:translateY(-4px);
}
.product-line-card .card-img {
  padding:35px 25px;
  background:linear-gradient(180deg, #fafafa 0%, #fff 100%);
  height:220px;
  display:flex;
  align-items:center;
  justify-content:center;
}
.product-line-card .card-img img {
  max-height:180px;
  max-width:75%;
  object-fit:contain;
}
.product-line-card .card-body {
  padding:22px 22px 28px;
  border-top:1px solid #f0f0f0;
}
.product-line-card .card-body h3 {
  font-size:17px;
  font-weight:700;
  margin:0 0 8px;
  color:#1a1a1a;
}
.product-line-card .card-body p {
  color:#888;
  font-size:13.5px;
  line-height:1.6;
  margin:0 0 18px;
}
.product-line-card .card-btn {
  color:#E50914;
  font-size:13px;
  font-weight:600;
  text-decoration:none;
  display:inline-flex;
  align-items:center;
  gap:6px;
  letter-spacing:0.3px;
  text-transform:uppercase;
}
.product-line-card .card-btn:hover {
  color:#E50914;
}
.product-line-card .card-btn:after {
  content:"\\2192";
  font-size:16px;
  transition:transform 0.2s;
}
.product-line-card .card-btn:hover:after {
  transform:translateX(3px);
}
</style>
[row h_align="center"]
[col span="3" span__sm="6" padding="12px"]
<div class="product-line-card">
<div class="card-img">
<img src="/wp-content/uploads/2024/09/hamilton_devices_ccell_th210-y_cartridge_-_black.png" alt="CCELL 510 Cartridges">
</div>
<div class="card-body">
<h3>510 Cartridges</h3>
<p>Industry standard 510 thread cartridges in multiple sizes and configurations.</p>
<a href="/product-category/cartridge/" class="card-btn">View Products</a>
</div>
</div>
[/col]
[col span="3" span__sm="6" padding="12px"]
<div class="product-line-card">
<div class="card-img">
<img src="/wp-content/uploads/2024/08/listo.png" alt="CCELL All-In-One Disposables">
</div>
<div class="card-body">
<h3>All-In-One Disposables</h3>
<p>Complete disposable units ready for your oil and your branding.</p>
<a href="/product-category/vaporizers/" class="card-btn">View Products</a>
</div>
</div>
[/col]
[col span="3" span__sm="6" padding="12px"]
<div class="product-line-card">
<div class="card-img">
<img src="/wp-content/uploads/2024/11/g2.png" alt="CCELL Pod Systems">
</div>
<div class="card-body">
<h3>Pod Systems</h3>
<p>Closed-loop pod systems for a proprietary branded experience.</p>
<a href="/product-category/pod-systems/" class="card-btn">View Products</a>
</div>
</div>
[/col]
[col span="3" span__sm="6" padding="12px"]
<div class="product-line-card">
<div class="card-img">
<img src="/wp-content/uploads/2024/12/Group-109.png" alt="CCELL Batteries">
</div>
<div class="card-body">
<h3>Batteries &amp; Power Supplies</h3>
<p>CCELL® batteries for retail and branded programs.</p>
<a href="/product-category/batteries/" class="card-btn">View Products</a>
</div>
</div>
[/col]
[/row]
[/section]

[section bg_color="rgb(248,248,248)" padding="60px"]
[row v_align="middle"]
[col span="6" span__sm="12"]
[ux_text font_size="1.1"]
<p style="text-transform:uppercase;letter-spacing:3px;color:#E50914;font-weight:600;font-size:13px;margin-bottom:10px;">Custom Branding</p>
[/ux_text]
[ux_text font_size="1.8"]
<h2 style="line-height:1.2;">Your Brand Identity on Every Piece of Hardware</h2>
[/ux_text]
[gap height="10px"]
[ux_text]
<p style="font-size:16px;line-height:1.8;color:#555;">We offer full custom branding on the entire CCELL® product line. Laser engraving, screen printing, and custom base colors — all produced at the CCELL® factory with your Pantone-matched artwork.</p>
[/ux_text]
[gap height="5px"]
[row_inner]
[col_inner span="6" span__sm="12"]
<ul style="list-style:none;padding:0;margin:0;">
<li style="padding:6px 0;font-size:15px;"><strong style="color:#E50914;">&#10003;</strong>&nbsp; Laser Engraving</li>
<li style="padding:6px 0;font-size:15px;"><strong style="color:#E50914;">&#10003;</strong>&nbsp; Screen Printing</li>
<li style="padding:6px 0;font-size:15px;"><strong style="color:#E50914;">&#10003;</strong>&nbsp; Custom Plastic Colors</li>
</ul>
[/col_inner]
[col_inner span="6" span__sm="12"]
<ul style="list-style:none;padding:0;margin:0;">
<li style="padding:6px 0;font-size:15px;"><strong style="color:#E50914;">&#10003;</strong>&nbsp; Pantone Color Matching</li>
<li style="padding:6px 0;font-size:15px;"><strong style="color:#E50914;">&#10003;</strong>&nbsp; Die-Line Templates Provided</li>
<li style="padding:6px 0;font-size:15px;"><strong style="color:#E50914;">&#10003;</strong>&nbsp; Factory Sample Approval</li>
</ul>
[/col_inner]
[/row_inner]
[gap height="15px"]
[button text="Learn More About Custom Branding" size="larger" link="/custom-branding/"]
[/col]
[col span="6" span__sm="12" align="center"]
[ux_image id="224085" image_size="original"]
[/col]
[/row]
[/section]

[section padding="0px"]
[row width="full-width" padding="0px"]
[col span__sm="12" padding="0px"]
<div style="background:linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%);padding:80px 40px;text-align:center;">
<p style="text-transform:uppercase;letter-spacing:5px;color:#E50914;font-weight:700;font-size:12px;margin-bottom:20px;">Start Your Partnership</p>
<h2 style="color:#fff;font-size:36px;font-weight:700;margin:0 0 15px;line-height:1.2;">Ready to Evaluate CCELL® for Your Brand?</h2>
<p style="color:rgba(255,255,255,0.6);font-size:17px;max-width:550px;margin:0 auto 35px;line-height:1.7;">Request complimentary samples and connect with our wholesale team. We will help you find the right hardware for your product line.</p>
<div style="display:flex;gap:15px;justify-content:center;flex-wrap:wrap;">
<a href="/request-samples/" style="background:#E50914;color:#fff;padding:14px 36px;border-radius:4px;font-weight:600;font-size:15px;text-decoration:none;display:inline-block;letter-spacing:0.5px;transition:all 0.3s;">Request Samples</a>
<a href="/contact/" style="background:transparent;color:#fff;padding:14px 36px;border-radius:4px;font-weight:600;font-size:15px;text-decoration:none;display:inline-block;letter-spacing:0.5px;border:1px solid rgba(255,255,255,0.3);">Contact Our Team</a>
</div>
</div>
[/col]
[/row]
[/section]

[section padding="10px"]
[row padding="26px 32px 1px 32px"]
[col span__sm="12"]
[title text="Industry News &amp; Resources" color="rgb(2, 2, 2)" class="esfergtre"]
[blog_posts style="normal" type="row" columns="3" columns__md="1" ids="210084,182454,162353" readmore="READ MORE" image_height="56.25%" image_size="original" text_align="left" text_size="large" class="frhyerftgf"]
[/col]
[/row]
[/section]

[section bg="224077" bg_size="original" padding="30px"]
[row col_bg="rgb(255, 255, 255)" col_bg_radius="8"]
[col span__sm="12" padding="20px 0px 0px 0px"]
[ux_text font_size="1.4" text_align="center"]
<h3>As Seen In</h3>
[/ux_text]
[row_inner h_align="center"]
[col_inner span="3" span__sm="6" align="center"]
[ux_image id="224067" image_size="original" width="54"]
[/col_inner]
[col_inner span="3" span__sm="6" align="center"]
[ux_image id="224068" image_size="original" width="54"]
[/col_inner]
[col_inner span="3" span__sm="6" align="center"]
[ux_image id="224069" image_size="original" width="64"]
[/col_inner]
[col_inner span="3" span__sm="6" align="center"]
[ux_image id="224070" image_size="original" width="65"]
[/col_inner]
[/row_inner]
[/col]
[/row]
[/section]

[row width="full-width" class="newsletter"]
[col span__sm="12"]
<p>[gravityform id="1" title="false" description="false"]</p>
[/col]
[/row]
[scroll_to link="#newsletter" bullet="false"]
[section class="letter"]
[row padding="15px 0px 15px 0px"]
[col span__sm="12"]
[block id="195409"]
[/col]
[/row]
[/section]';

$home_id = get_option("page_on_front");
if ($home_id) {
    // Replace Docker image IDs if any new ones exist
    $updated_content = hd_replace_image_ids($homepage_content, $docker_to_prod_id);
    
    wp_update_post(array(
        "ID" => $home_id,
        "post_content" => $updated_content,
    ));
    mlog("  Updated homepage content (ID: $home_id)");
} else {
    mlog("  WARNING: No static front page set");
}
mlog("");


// ============================================================
// SECTION 9: CSS Color Fix
// ============================================================
mlog("--- Section 9: CSS Color Fix ---");

$old_reds = array(
    "#c72035", "#C72035",
    "#E2181F", "#e2181f",
    "#C72034", "#c72034",
    "#cc202c", "#CC202C",
    "#cc0033", "#CC0033",
    "#AE0C1F", "#ae0c1f",
    "#c9182b", "#C9182B",
    "#E62037", "#e62037",
    "#E52037", "#e52037",
    "#de0e1d", "#DE0E1D",
    "#dc143c", "#DC143C",
    "#c62035", "#C62035",
    "#b81024", "#B81024",
    "#d00404", "#D00404",
    "#a01a2c", "#A01A2C",
);
$new_red = "#E50914";

$css_post = wp_get_custom_css_post();
if ($css_post) {
    $css = $css_post->post_content;
    $original_len = strlen($css);
    
    foreach ($old_reds as $old) {
        $css = str_replace($old, $new_red, $css);
    }
    
    if ($css !== $css_post->post_content) {
        wp_update_custom_css_post($css);
        mlog("  Updated custom CSS — replaced old red variants with $new_red");
    } else {
        mlog("  SKIP: No old red colors found in custom CSS");
    }
} else {
    mlog("  SKIP: No custom CSS post found");
}
mlog("");


// ============================================================
// SECTION 10: Topbar & Theme Options
// ============================================================
mlog("--- Section 10: Topbar & Theme Options ---");

$current_topbar = get_theme_mod("topbar_left");
$new_topbar = "Authorized CCELL\u{00AE} Distributor | Custom Branding Available | Request Samples Today";

if ($current_topbar !== $new_topbar) {
    set_theme_mod("topbar_left", $new_topbar);
    mlog("  Updated topbar text");
} else {
    mlog("  SKIP: Topbar text already set");
}

// Ensure topbar background is brand red
$topbar_bg = get_theme_mod("topbar_bg");
if ($topbar_bg !== "#E50914") {
    set_theme_mod("topbar_bg", "#E50914");
    mlog("  Updated topbar background to #E50914");
}

// Ensure header shop bg is brand red
$header_shop_bg = get_theme_mod("header_shop_bg_color");
if ($header_shop_bg !== "#E50914") {
    set_theme_mod("header_shop_bg_color", "#E50914");
    mlog("  Updated header shop bg to #E50914");
}

mlog("");


// ============================================================
// SECTION 11: Recount & Flush
// ============================================================
mlog("--- Section 11: Recount & Flush ---");

// Recount all product categories
$terms = get_terms(array("taxonomy" => "product_cat", "hide_empty" => false, "fields" => "ids"));
if (!is_wp_error($terms)) {
    wp_update_term_count_now($terms, "product_cat");
    mlog("  Recounted " . count($terms) . " product categories");
}

// Clear WooCommerce transients
delete_transient("wc_products_onsale");
delete_transient("wc_featured_products");
delete_transient("wc_count_comments");

// Clear product count transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wc_%'");

// Flush rewrite rules
flush_rewrite_rules();
mlog("  Flushed rewrite rules");
mlog("  Cleared WooCommerce transients");

mlog("");
mlog("=== Migration Complete ===");
mlog("Finished: " . date("Y-m-d H:i:s"));
mlog("");
mlog("NEXT STEPS:");
mlog("  1. Run: wp rewrite flush");
mlog("  2. Clear all caches (page cache, object cache, CDN)");
mlog("  3. Visit /product-category/cartridge/ — verify technology selector cards");
mlog("  4. Visit /product-category/disposable/ — verify AIO technology cards");
mlog("  5. Visit /shop-hamilton/ — verify full product catalog");
mlog("  6. Visit /ccell-heating-technology/ — verify comparison page");
mlog("  7. Check mega menu — verify Technology dropdown");
mlog("  8. Check a product with launch file slider — verify carousel");
mlog("  9. Verify archived products are hidden from shop");
mlog(" 10. Check custom CSS — all reds should be #E50914");

