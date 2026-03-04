<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// ============================================================
// 1. Create Request Samples CF7 Form
// ============================================================
$cf7_content = '<div class="form-flat">
<h4>Contact Information</h4>

<label>Company Name *</label>
[text* company-name placeholder "Your company name"]

<label>Contact Name *</label>
[text* contact-name placeholder "Your full name"]

<label>Email Address *</label>
[email* your-email placeholder "Email address"]

<label>Phone Number *</label>
[tel* phone-number placeholder "Phone number"]

<label>Company Website</label>
[url company-website placeholder "https://yourcompany.com"]

<h4>Sample Request Details</h4>

<label>What products are you interested in? *</label>
[checkbox* product-interest "CCELL Cartridges (510 Thread)" "CCELL All-In-One Disposables" "CCELL Pod Systems" "CCELL Batteries/Power Supplies" "Custom Branding/OEM"]

<label>Estimated Monthly Volume</label>
[select volume "Select volume range" "Under 1,000 units" "1,000 - 5,000 units" "5,000 - 10,000 units" "10,000 - 50,000 units" "50,000+ units"]

<label>Are you interested in custom branding?</label>
[select custom-branding "Select one" "Yes - I need my logo/branding on the hardware" "Not yet - just evaluating products first" "No - I will use standard/unbranded"]

<label>Tell us about your business and what you are looking for *</label>
[textarea* business-details placeholder "Tell us about your brand, what market you serve, and what you are looking for."]

[submit class:button primary "Request Samples"]
</div>';

$stmt = $conn->prepare("INSERT INTO wp_posts (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count) VALUES (1, NOW(), NOW(), ?, 'Request Samples Form', '', 'publish', 'closed', 'closed', '', 'request-samples-form', '', '', NOW(), NOW(), '', 0, '', 0, 'wpcf7_contact_form', '', 0)");
$stmt->bind_param('s', $cf7_content);
$stmt->execute();
$form_id = $conn->insert_id;
echo "Created CF7 form ID: $form_id\n";

// ============================================================
// 2. Create Custom Branding Page
// ============================================================
$custom_branding_content = '[section bg_color="rgb(0,0,0)" padding="80px" class="custom-branding-hero"]
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
[featured_box img_width="60" pos="center" icon_color="rgb(26,26,26)"]
<h3>Laser Engraving</h3>
<p>Precision laser engraving on metal and glass components. Creates a permanent, premium-feel mark on mouthpieces, housings, and cartridge bodies.</p>
[/featured_box]
[/col]
[col span="4" span__sm="12" padding="30px" align="center"]
[featured_box img_width="60" pos="center" icon_color="rgb(26,26,26)"]
<h3>Screen Printing</h3>
<p>Full-color screen printing on metal, glass, and plastic surfaces. Pantone color matching ensures your brand colors are represented exactly as intended.</p>
[/featured_box]
[/col]
[col span="4" span__sm="12" padding="30px" align="center"]
[featured_box img_width="60" pos="center" icon_color="rgb(26,26,26)"]
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
[button text="Request Samples" color="white" style="outline" size="larger" link="/request-samples/"]
[button text="Contact Our Team" color="white" size="larger" link="/contact/"]
[/col]
[/row]
[/section]';

$stmt = $conn->prepare("INSERT INTO wp_posts (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count) VALUES (1, NOW(), NOW(), ?, 'Custom Branding', '', 'publish', 'closed', 'closed', '', 'custom-branding', '', '', NOW(), NOW(), '', 0, '', 0, 'page', '', 0)");
$stmt->bind_param('s', $custom_branding_content);
$stmt->execute();
$branding_page_id = $conn->insert_id;
echo "Created Custom Branding page ID: $branding_page_id\n";

// Update guid
$conn->query("UPDATE wp_posts SET guid='http://localhost:8080/?page_id=$branding_page_id' WHERE ID=$branding_page_id");

// Add page template meta
$conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($branding_page_id, '_wp_page_template', 'page-blank.php')");

// ============================================================
// 3. Create Request Samples Page
// ============================================================
$samples_content = '[section bg_color="rgb(0,0,0)" padding="60px" class="samples-hero"]
[row]
[col span="7" span__sm="12"]
[ux_text font_size="1.1" text_color="rgb(255,255,255)"]
<p style="text-transform:uppercase;letter-spacing:3px;color:#E50914;font-weight:600;margin-bottom:10px;">Authorized CCELL® Distributor</p>
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

[contact-form-7 id="' . $form_id . '" title="Request Samples Form"]

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
[/section]';

$stmt = $conn->prepare("INSERT INTO wp_posts (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count) VALUES (1, NOW(), NOW(), ?, 'Request Samples', '', 'publish', 'closed', 'closed', '', 'request-samples', '', '', NOW(), NOW(), '', 0, '', 0, 'page', '', 0)");
$stmt->bind_param('s', $samples_content);
$stmt->execute();
$samples_page_id = $conn->insert_id;
echo "Created Request Samples page ID: $samples_page_id\n";

$conn->query("UPDATE wp_posts SET guid='http://localhost:8080/?page_id=$samples_page_id' WHERE ID=$samples_page_id");
$conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($samples_page_id, '_wp_page_template', 'page-blank.php')");

// ============================================================
// 4. Update Navigation Menu (term_id=67, "Main" menu)
// ============================================================

// First, get the current max menu_order
$result = $conn->query("SELECT MAX(menu_order) as max_order FROM wp_posts p JOIN wp_term_relationships tr ON p.ID=tr.object_id JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id=tt.term_taxonomy_id WHERE p.post_type='nav_menu_item' AND tt.term_id=67");
$max = $result->fetch_assoc()['max_order'];
echo "Current max menu_order: $max\n";

// Remove Deals (ID 35613), Reviews (ID 74218) from menu by changing their status
$conn->query("UPDATE wp_posts SET post_status='draft' WHERE ID IN (35613, 74218)");
echo "Removed Deals and Reviews from nav\n";

// Rename Shop (menu item 74568) to Products - update post_title
$conn->query("UPDATE wp_posts SET post_title='Products' WHERE ID=74568");
echo "Renamed Shop to Products\n";

// Rename CCELL menu item (108610) - move it under Products
// First get the menu_item_menu_item_parent for item 108610 and set it to 74568 (Products)
$conn->query("UPDATE wp_postmeta SET meta_value='74568' WHERE post_id=108610 AND meta_key='_menu_item_menu_item_parent'");
$conn->query("UPDATE wp_posts SET menu_order=3 WHERE ID=108610");
echo "Moved CCELL under Products\n";

// Create Custom Branding nav menu item
$conn->query("INSERT INTO wp_posts (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count) VALUES (1, NOW(), NOW(), '', 'Custom Branding', '', 'publish', 'closed', 'closed', '', '', '', '', NOW(), NOW(), '', 0, '', 101, 'nav_menu_item', '', 0)");
$cb_nav_id = $conn->insert_id;
$conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES
($cb_nav_id, '_menu_item_type', 'post_type'),
($cb_nav_id, '_menu_item_menu_item_parent', '0'),
($cb_nav_id, '_menu_item_object_id', '$branding_page_id'),
($cb_nav_id, '_menu_item_object', 'page'),
($cb_nav_id, '_menu_item_target', ''),
($cb_nav_id, '_menu_item_classes', 'a:1:{i:0;s:0:\"\";}'),
($cb_nav_id, '_menu_item_xfn', ''),
($cb_nav_id, '_menu_item_url', '')");
// Add to Main menu (term_id=67, term_taxonomy_id for nav_menu)
$result = $conn->query("SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE term_id=67 AND taxonomy='nav_menu'");
$tt_id = $result->fetch_assoc()['term_taxonomy_id'];
$conn->query("INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($cb_nav_id, $tt_id, 0)");
echo "Created Custom Branding nav item ID: $cb_nav_id\n";

// Create Request Samples nav menu item
$conn->query("INSERT INTO wp_posts (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count) VALUES (1, NOW(), NOW(), '', 'Request Samples', '', 'publish', 'closed', 'closed', '', '', '', '', NOW(), NOW(), '', 0, '', 102, 'nav_menu_item', '', 0)");
$rs_nav_id = $conn->insert_id;
$conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES
($rs_nav_id, '_menu_item_type', 'post_type'),
($rs_nav_id, '_menu_item_menu_item_parent', '0'),
($rs_nav_id, '_menu_item_object_id', '$samples_page_id'),
($rs_nav_id, '_menu_item_object', 'page'),
($rs_nav_id, '_menu_item_target', ''),
($rs_nav_id, '_menu_item_classes', 'a:1:{i:0;s:0:\"\";}'),
($rs_nav_id, '_menu_item_xfn', ''),
($rs_nav_id, '_menu_item_url', '')");
$conn->query("INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($rs_nav_id, $tt_id, 0)");
echo "Created Request Samples nav item ID: $rs_nav_id\n";

// Reorder: Home=1, Products=2, Custom Branding=101, Request Samples=102, News=103, Contact=104, Wholesale Login=200
$conn->query("UPDATE wp_posts SET menu_order=1 WHERE ID=18022");   // Home
$conn->query("UPDATE wp_posts SET menu_order=2 WHERE ID=74568");   // Products (was Shop)
$conn->query("UPDATE wp_posts SET menu_order=101 WHERE ID=$cb_nav_id");  // Custom Branding
$conn->query("UPDATE wp_posts SET menu_order=102 WHERE ID=$rs_nav_id");  // Request Samples
$conn->query("UPDATE wp_posts SET menu_order=103 WHERE ID=70993");  // News
$conn->query("UPDATE wp_posts SET menu_order=104 WHERE ID=322");    // Contact
$conn->query("UPDATE wp_posts SET menu_order=200 WHERE ID=142297"); // Wholesale Login (far right)
echo "Reordered menu items\n";

// Update the menu item count in term_taxonomy
$conn->query("UPDATE wp_term_taxonomy SET count = count + 2 WHERE term_id = 67 AND taxonomy = 'nav_menu'");

// ============================================================
// 5. Update Top Bar Text
// ============================================================
$result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='theme_mods_flatsome-child'");
$row = $result->fetch_assoc();
$mods = unserialize($row['option_value']);
$mods['topbar_left'] = 'Authorized CCELL® Distributor | Custom Branding Available | Request Samples Today';
$new_mods = serialize($mods);
$stmt = $conn->prepare("UPDATE wp_options SET option_value=? WHERE option_name='theme_mods_flatsome-child'");
$stmt->bind_param('s', $new_mods);
$stmt->execute();
echo "Updated top bar text\n";

echo "\nDone! All pages, forms, and nav updates created.\n";
echo "Custom Branding page: /custom-branding/ (ID: $branding_page_id)\n";
echo "Request Samples page: /request-samples/ (ID: $samples_page_id)\n";
echo "CF7 Form ID: $form_id\n";

$conn->close();
