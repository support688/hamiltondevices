<?php
/**
 * Create 5 missing products from final purchase order + fix 2 existing TH210-SE listings
 *
 * Part 1: Fix TH210-SE 221042 (White Ceramic) & 221044 (Black Ceramic)
 *         — add descriptions, create ccell-se subcategory, assign SE variants
 * Part 2: Create Mini Tank SE 1.0ml, Mini Tank 2.0 2.0ml, Mini Tank EM 1.0ml,
 *         Rosin Bar 1.0ml, Bravo (placeholder)
 *
 * Run via: docker compose exec -T wordpress php < create-order-products.php
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Create Order Products & Fix TH210-SE ===\n\n";

// =============================================================================
// Helpers
// =============================================================================

function get_tt_id_by_slug($conn, $slug, $taxonomy = 'product_cat') {
    $stmt = $conn->prepare("SELECT tt.term_taxonomy_id FROM wp_term_taxonomy tt JOIN wp_terms t ON t.term_id=tt.term_id WHERE t.slug=? AND tt.taxonomy=? LIMIT 1");
    $stmt->bind_param('ss', $slug, $taxonomy);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? $row['term_taxonomy_id'] : null;
}

function get_tt_id_by_term_id($conn, $term_id, $taxonomy = 'product_cat') {
    $stmt = $conn->prepare("SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE term_id=? AND taxonomy=?");
    $stmt->bind_param('is', $term_id, $taxonomy);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? $row['term_taxonomy_id'] : null;
}

function slug_exists($conn, $slug) {
    $stmt = $conn->prepare("SELECT ID FROM wp_posts WHERE post_name=? AND post_type='product' LIMIT 1");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function create_product($conn, $p, $categories, $tt_simple) {
    if (slug_exists($conn, $p['slug'])) {
        echo "  SKIP: Slug '{$p['slug']}' already exists\n";
        return null;
    }

    $now = date('Y-m-d H:i:s');
    $now_gmt = gmdate('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO wp_posts (
        post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
        post_status, comment_status, ping_status, post_password, post_name,
        to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered,
        post_parent, guid, menu_order, post_type, post_mime_type, comment_count
    ) VALUES (
        1, ?, ?, ?, ?, ?,
        'publish', 'open', 'closed', '', ?,
        '', '', ?, ?, '',
        0, '', 0, 'product', '', 0
    )");
    $stmt->bind_param('ssssssss', $now, $now_gmt, $p['description'], $p['title'], $p['short_desc'], $p['slug'], $now, $now_gmt);
    $stmt->execute();
    $post_id = $conn->insert_id;

    $guid = "http://localhost:8080/?post_type=product&#038;p=$post_id";
    $conn->query("UPDATE wp_posts SET guid='$guid' WHERE ID=$post_id");

    // Taxonomy
    $all_cats = array_merge($categories, [$tt_simple]);
    foreach ($all_cats as $tt_id) {
        if ($tt_id) {
            $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($post_id, $tt_id, 0)");
        }
    }

    // Meta
    $meta = [
        '_sku' => $p['sku'], '_regular_price' => $p['price'], '_price' => $p['price'],
        '_stock_status' => 'instock', '_manage_stock' => 'no', '_stock' => '',
        '_backorders' => 'no', '_sold_individually' => 'no', '_virtual' => 'no',
        '_downloadable' => 'no', '_download_limit' => '-1', '_download_expiry' => '-1',
        '_tax_status' => 'taxable', '_tax_class' => '', '_product_version' => '10.4.3',
        'total_sales' => '0', '_visibility' => 'visible', '_disabled_for_coupons' => 'no',
        '_bulkdiscount_enabled' => 'yes', 'table_name' => 'Bulk Pricing',
        '1st_column_name' => 'Quantity', '2nd_column_name' => 'Price per unit',
        'price_text' => 'As low as', 'lowest_price' => $p['lowest'],
        'wholesale_customer_have_wholesale_price' => 'yes',
        'wholesale_customer_wholesale_price' => $p['wholesale_price'],
        'wholesale_customer_wholesale_minimum_order_quantity' => '100',
        'wholesale_customer_wholesale_order_quantity_step' => '100',
        'wtable_name' => 'Wholesale Pricing', '1st_wcolumn_name' => 'MOQ',
        '2nd_wcolumn_name' => 'Unit Price', 'wprice_text' => 'As low as',
        'wlowest_price' => $p['lowest'],
        'wwpp_post_meta_enable_quantity_discount_rule' => 'yes',
        'wwpp_product_wholesale_visibility_filter' => 'all',
        'wwpp_ignore_cat_level_wholesale_discount' => 'no',
        'wwpp_ignore_role_level_wholesale_discount' => 'no',
        '_yoast_wpseo_metadesc' => $p['meta_desc'],
        '_yoast_wpseo_content_score' => '90',
        '_yoast_wpseo_primary_product_cat' => '1234',
    ];

    $meta_stmt = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)");
    foreach ($meta as $key => $value) {
        $val = $value ?? '';
        $meta_stmt->bind_param('iss', $post_id, $key, $val);
        $meta_stmt->execute();
    }

    // Retail tiers
    foreach ($p['retail_tiers'] as $i => $tier) {
        $num = $i + 1;
        $k1 = "quantity_limit_$num"; $k2 = "price_per_unit_$num";
        $meta_stmt->bind_param('iss', $post_id, $k1, $tier['range']);
        $meta_stmt->execute();
        $meta_stmt->bind_param('iss', $post_id, $k2, $tier['price']);
        $meta_stmt->execute();
    }

    // Wholesale tiers
    foreach ($p['wholesale_tiers'] as $i => $tier) {
        $num = $i + 1;
        $wprice = '$' . $tier['price'];
        $wqty = empty($tier['end']) ? "{$tier['start']}+" : "{$tier['start']}–{$tier['end']}";
        $k1 = "wquantity_limit_$num"; $k2 = "wprice_per_unit_$num";
        $meta_stmt->bind_param('iss', $post_id, $k1, $wqty);
        $meta_stmt->execute();
        $meta_stmt->bind_param('iss', $post_id, $k2, $wprice);
        $meta_stmt->execute();
    }
    $conn->query("UPDATE wp_postmeta SET meta_value='100–1,999' WHERE post_id=$post_id AND meta_key='wquantity_limit_1'");
    $conn->query("UPDATE wp_postmeta SET meta_value='2,000+' WHERE post_id=$post_id AND meta_key='wquantity_limit_2'");

    // Wholesale mapping
    $ww = serialize([
        ['wholesale_role'=>'wholesale_customer','start_qty'=>$p['wholesale_tiers'][0]['start'],'end_qty'=>$p['wholesale_tiers'][0]['end'],'price_type'=>'fixed-price','wholesale_price'=>$p['wholesale_tiers'][0]['price']],
        ['wholesale_role'=>'wholesale_customer','start_qty'=>$p['wholesale_tiers'][1]['start'],'end_qty'=>$p['wholesale_tiers'][1]['end'],'price_type'=>'fixed-price','wholesale_price'=>$p['wholesale_tiers'][1]['price']],
    ]);
    $k = 'wwpp_post_meta_quantity_discount_rule_mapping';
    $meta_stmt->bind_param('iss', $post_id, $k, $ww);
    $meta_stmt->execute();

    // Flatsome
    $fo = serialize([[
        '_product_block'=>'0','_top_content'=>'','_bottom_content'=>'','_bubble_new'=>'',
        '_bubble_text'=>'','_custom_tab_title'=>'','_custom_tab'=>'','_product_video'=>'',
        '_product_video_size'=>'','_product_video_placement'=>'',
    ]]);
    $k = 'wc_productdata_options';
    $meta_stmt->bind_param('iss', $post_id, $k, $fo);
    $meta_stmt->execute();

    return $post_id;
}

function download_image($conn, $url, $filename, $title, $product_id, $upload_dir, $year_month) {
    $local_path = "$upload_dir/$filename";
    $relative_path = "$year_month/$filename";

    if (file_exists($local_path) && filesize($local_path) > 1000) {
        $check = $conn->prepare("SELECT p.ID FROM wp_posts p JOIN wp_postmeta pm ON p.ID=pm.post_id WHERE pm.meta_key='_wp_attached_file' AND pm.meta_value=? AND p.post_type='attachment' LIMIT 1");
        $check->bind_param('s', $relative_path);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        if ($row) return $row['ID'];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; HamiltonDevices/1.0)');
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($data) || strlen($data) < 1000) return false;

    file_put_contents($local_path, $data);
    $img_info = getimagesize($local_path);
    $width = $img_info[0] ?? 0;
    $height = $img_info[1] ?? 0;
    $mime_type = $img_info['mime'] ?? 'image/jpeg';

    $now = date('Y-m-d H:i:s');
    $now_gmt = gmdate('Y-m-d H:i:s');
    $slug = pathinfo($filename, PATHINFO_FILENAME);
    $guid = "http://localhost:8080/wp-content/uploads/$relative_path";

    $stmt = $conn->prepare("INSERT INTO wp_posts (
        post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
        post_status, comment_status, ping_status, post_password, post_name,
        to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered,
        post_parent, guid, menu_order, post_type, post_mime_type, comment_count
    ) VALUES (
        1, ?, ?, '', ?, '',
        'inherit', 'open', 'closed', '', ?,
        '', '', ?, ?, '',
        ?, ?, 0, 'attachment', ?, 0
    )");
    $stmt->bind_param('ssssssiss', $now, $now_gmt, $title, $slug, $now, $now_gmt, $product_id, $guid, $mime_type);
    $stmt->execute();
    $attach_id = $conn->insert_id;
    if (!$attach_id) return false;

    $stmt2 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_wp_attached_file', ?)");
    $stmt2->bind_param('is', $attach_id, $relative_path);
    $stmt2->execute();

    $meta = serialize(['width'=>$width,'height'=>$height,'file'=>$relative_path,'sizes'=>[],'image_meta'=>['aperture'=>'0','credit'=>'','camera'=>'','caption'=>'','created_timestamp'=>'0','copyright'=>'','focal_length'=>'0','iso'=>'0','shutter_speed'=>'0','title'=>'','orientation'=>'0','keywords'=>[]]]);
    $stmt3 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_wp_attachment_metadata', ?)");
    $stmt3->bind_param('is', $attach_id, $meta);
    $stmt3->execute();

    return $attach_id;
}

function set_thumbnail($conn, $post_id, $attach_id) {
    $existing = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=$post_id AND meta_key='_thumbnail_id' LIMIT 1");
    if ($existing->num_rows > 0) {
        $conn->query("UPDATE wp_postmeta SET meta_value='$attach_id' WHERE post_id=$post_id AND meta_key='_thumbnail_id'");
    } else {
        $conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($post_id, '_thumbnail_id', '$attach_id')");
    }
}

function set_gallery($conn, $post_id, $gallery_ids) {
    if (empty($gallery_ids)) return;
    $gallery_str = implode(',', $gallery_ids);
    $existing = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=$post_id AND meta_key='_product_image_gallery' LIMIT 1");
    if ($existing->num_rows > 0) {
        $conn->query("UPDATE wp_postmeta SET meta_value='$gallery_str' WHERE post_id=$post_id AND meta_key='_product_image_gallery'");
    } else {
        $conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($post_id, '_product_image_gallery', '$gallery_str')");
    }
}

// =============================================================================
// Category TT IDs
// =============================================================================

$tt_cartridge  = get_tt_id_by_term_id($conn, 543);
$tt_disposable = get_tt_id_by_term_id($conn, 550);
$tt_ccell      = get_tt_id_by_term_id($conn, 1234);
$tt_simple     = get_tt_id_by_term_id($conn, 2, 'product_type');
$tt_aio_se     = get_tt_id_by_slug($conn, 'aio-se-standard');
$tt_aio_evomax = get_tt_id_by_slug($conn, 'aio-evo-max');
$tt_aio_hero   = get_tt_id_by_slug($conn, 'aio-hero');

$year_month = date('Y/m');
$upload_dir = "/var/www/html/wp-content/uploads/$year_month";
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

echo "Category TT IDs:\n";
echo "  cartridge=$tt_cartridge, disposable=$tt_disposable, ccell=$tt_ccell\n";
echo "  aio_se=$tt_aio_se, aio_evomax=$tt_aio_evomax, aio_hero=$tt_aio_hero\n\n";

// =============================================================================
// PART 1: Create ccell-se subcategory + Fix TH210-SE products
// =============================================================================

echo "══ PART 1: Fix TH210-SE Products ══\n\n";

// ── Step 1a: Create ccell-se subcategory under Cartridges (543) ──

echo "── Step 1a: Create ccell-se subcategory ──\n";

$tt_ccell_se = get_tt_id_by_slug($conn, 'ccell-se');
if (!$tt_ccell_se) {
    // Insert new term
    $conn->query("INSERT INTO wp_terms (name, slug, term_group) VALUES ('CCELL SE Glass', 'ccell-se', 0)");
    $new_term_id = $conn->insert_id;

    // Insert term_taxonomy with parent = 543 (Cartridges)
    $conn->query("INSERT INTO wp_term_taxonomy (term_id, taxonomy, description, parent, count) VALUES ($new_term_id, 'product_cat', 'CCELL SE platform glass cartridges with screw-on mouthpiece. 1.4 ohm resistance, 510 thread, optimized for distillate.', 543, 0)");
    $tt_ccell_se = $conn->insert_id;

    echo "  Created: CCELL SE Glass (term_id=$new_term_id, tt_id=$tt_ccell_se)\n";
} else {
    echo "  EXISTS: ccell-se already exists (tt_id=$tt_ccell_se)\n";
}

// ── Step 1b: Fix 221042 (White Ceramic) ──

echo "\n── Step 1b: Fix TH210-SE White Ceramic (221042) ──\n";

$th210se_desc = 'The CCELL TH210-SE is a 1.0ml glass-body cartridge built on the SE heating platform — the original CCELL ceramic coil that established the industry standard for distillate vaporization. The screw-on ceramic mouthpiece provides a premium feel and secure seal.

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
</ul>';

$th210se_short_white = 'CCELL TH210-SE 1.0ml glass cartridge with SE ceramic heating and white ceramic screw-on mouthpiece. 1.4 ohm, 510 thread, distillate-optimized.';
$th210se_meta_white = 'CCELL TH210-SE 1.0ml glass cartridge with white ceramic mouthpiece. SE heating, 1.4 ohm, 510 thread. Wholesale from Hamilton Devices.';

$now = date('Y-m-d H:i:s');
$now_gmt = gmdate('Y-m-d H:i:s');

$stmt = $conn->prepare("UPDATE wp_posts SET post_content=?, post_excerpt=?, post_modified=?, post_modified_gmt=? WHERE ID=?");
$id_white = 221042;
$stmt->bind_param('ssssi', $th210se_desc, $th210se_short_white, $now, $now_gmt, $id_white);
$stmt->execute();
echo "  Updated description & short description for 221042\n";

// Yoast meta — insert or update
$check = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=221042 AND meta_key='_yoast_wpseo_metadesc' LIMIT 1");
if ($check->num_rows > 0) {
    $stmt2 = $conn->prepare("UPDATE wp_postmeta SET meta_value=? WHERE post_id=221042 AND meta_key='_yoast_wpseo_metadesc'");
    $stmt2->bind_param('s', $th210se_meta_white);
    $stmt2->execute();
} else {
    $stmt2 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (221042, '_yoast_wpseo_metadesc', ?)");
    $stmt2->bind_param('s', $th210se_meta_white);
    $stmt2->execute();
}
// Also set Yoast content score and primary cat
$conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) SELECT 221042, '_yoast_wpseo_content_score', '90' FROM dual WHERE NOT EXISTS (SELECT 1 FROM wp_postmeta WHERE post_id=221042 AND meta_key='_yoast_wpseo_content_score')");
$conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) SELECT 221042, '_yoast_wpseo_primary_product_cat', '1234' FROM dual WHERE NOT EXISTS (SELECT 1 FROM wp_postmeta WHERE post_id=221042 AND meta_key='_yoast_wpseo_primary_product_cat')");

echo "  Set Yoast SEO meta for 221042\n";

// ── Step 1c: Fix 221044 (Black Ceramic) ──

echo "\n── Step 1c: Fix TH210-SE Black Ceramic (221044) ──\n";

$th210se_short_black = 'CCELL TH210-SE 1.0ml glass cartridge with SE ceramic heating and black ceramic screw-on mouthpiece. 1.4 ohm, 510 thread, distillate-optimized.';
$th210se_meta_black = 'CCELL TH210-SE 1.0ml glass cartridge with black ceramic mouthpiece. SE heating, 1.4 ohm, 510 thread. Wholesale from Hamilton Devices.';

$id_black = 221044;
$stmt->bind_param('ssssi', $th210se_desc, $th210se_short_black, $now, $now_gmt, $id_black);
$stmt->execute();
echo "  Updated description & short description for 221044\n";

$check = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=221044 AND meta_key='_yoast_wpseo_metadesc' LIMIT 1");
if ($check->num_rows > 0) {
    $stmt2 = $conn->prepare("UPDATE wp_postmeta SET meta_value=? WHERE post_id=221044 AND meta_key='_yoast_wpseo_metadesc'");
    $stmt2->bind_param('s', $th210se_meta_black);
    $stmt2->execute();
} else {
    $stmt2 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (221044, '_yoast_wpseo_metadesc', ?)");
    $stmt2->bind_param('s', $th210se_meta_black);
    $stmt2->execute();
}
$conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) SELECT 221044, '_yoast_wpseo_content_score', '90' FROM dual WHERE NOT EXISTS (SELECT 1 FROM wp_postmeta WHERE post_id=221044 AND meta_key='_yoast_wpseo_content_score')");
$conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) SELECT 221044, '_yoast_wpseo_primary_product_cat', '1234' FROM dual WHERE NOT EXISTS (SELECT 1 FROM wp_postmeta WHERE post_id=221044 AND meta_key='_yoast_wpseo_primary_product_cat')");

echo "  Set Yoast SEO meta for 221044\n";

// ── Step 1d: Assign all SE screw-on variants to ccell-se + Cartridges + ccell ──

echo "\n── Step 1d: Assign SE variants to ccell-se subcategory ──\n";

$se_product_ids = [229958, 215322, 218064, 221042, 221044, 221047, 221058, 221055, 221059, 228928];
$assign_tt_ids = array_filter([$tt_ccell_se, $tt_cartridge, $tt_ccell]);

foreach ($se_product_ids as $pid) {
    foreach ($assign_tt_ids as $tt_id) {
        $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($pid, $tt_id, 0)");
    }
    $title_row = $conn->query("SELECT post_title FROM wp_posts WHERE ID=$pid")->fetch_assoc();
    $title_short = $title_row ? substr($title_row['post_title'], 0, 50) : "ID:$pid";
    echo "  Assigned: $pid ($title_short...)\n";
}

// =============================================================================
// PART 2: Create 5 New Products
// =============================================================================

echo "\n══ PART 2: Create New Products ══\n\n";

$AIO_PRICING = [
    'price' => '9.99', 'lowest' => '5.99', 'wholesale_price' => '6.99',
    'retail_tiers' => [
        ['range' => '1-19',       'price' => '$9.99'],
        ['range' => '20-49',      'price' => '$8.49'],
        ['range' => '50-99',      'price' => '$7.49'],
        ['range' => '100-1,999',  'price' => '$6.99'],
        ['range' => '2,000+',     'price' => '$5.99'],
    ],
    'wholesale_tiers' => [
        ['start' => '100', 'end' => '1999', 'price' => '6.99'],
        ['start' => '2000', 'end' => '',     'price' => '5.99'],
    ],
];

// ────────────────────────────────────────────────────────────────────────
// 2a. CCELL Mini Tank SE — 1.0ml AIO (black)
// ────────────────────────────────────────────────────────────────────────

$products = [];

$products[] = [
    'data' => array_merge($AIO_PRICING, [
        'title'       => 'CCELL Mini Tank SE — 1.0ml All-In-One',
        'slug'        => 'ccell-mini-tank-se-1-0ml-aio',
        'sku'         => 'MINITANK-SE-10',
        'description' => 'The CCELL Mini Tank SE is an ultra-compact 1.0ml all-in-one disposable built on the SE ceramic heating platform — the original CCELL coil technology trusted across the industry for smooth, reliable distillate vaporization.

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
</ul>',
        'short_desc'  => 'CCELL Mini Tank SE 1.0ml AIO with SE ceramic heating, 200mAh USB-C battery, Aroma Seal, and clog-free dual vents. Ultra-compact at 63mm.',
        'meta_desc'   => 'CCELL Mini Tank SE 1.0ml all-in-one with SE ceramic heating and 200mAh USB-C battery. Ultra-compact disposable. Wholesale from Hamilton Devices.',
    ]),
    'categories' => [$tt_disposable, $tt_ccell, $tt_aio_se],
    'hero_url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2024/12/01_Jupiter_CCELL_AIO_MiniTank_NoAC.jpg',
    'hero_file'  => 'ccell-mini-tank-se-1ml-hero.jpg',
    'hero_title' => 'CCELL Mini Tank SE 1.0ml',
    'gallery'    => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-01.jpg', 'ccell-mini-tank-gallery-01.jpg', 'CCELL Mini Tank Front'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-02.jpg', 'ccell-mini-tank-gallery-02.jpg', 'CCELL Mini Tank Side'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-03.jpg', 'ccell-mini-tank-gallery-03.jpg', 'CCELL Mini Tank Angle'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-04.jpg', 'ccell-mini-tank-gallery-04.jpg', 'CCELL Mini Tank Back'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-05.jpg', 'ccell-mini-tank-gallery-05.jpg', 'CCELL Mini Tank USB-C'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-07.jpg', 'ccell-mini-tank-gallery-07.jpg', 'CCELL Mini Tank Top'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-08.jpg', 'ccell-mini-tank-gallery-08.jpg', 'CCELL Mini Tank Detail'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-09.jpg', 'ccell-mini-tank-gallery-09.jpg', 'CCELL Mini Tank Overview'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/10_Jupiter_CCELL_AIO_MiniTank_Size.jpg', 'ccell-mini-tank-gallery-size.jpg', 'CCELL Mini Tank Size Comparison'],
    ],
];

// ────────────────────────────────────────────────────────────────────────
// 2b. CCELL Mini Tank 2.0 — 2.0ml AIO (black)
// ────────────────────────────────────────────────────────────────────────

$products[] = [
    'data' => array_merge($AIO_PRICING, [
        'title'       => 'CCELL Mini Tank 2.0 — 2.0ml All-In-One',
        'slug'        => 'ccell-mini-tank-2-0-2ml-aio',
        'sku'         => 'MINITANK-20-20',
        'description' => 'The CCELL Mini Tank 2.0 is the high-capacity version of the Mini Tank platform, packing 2.0ml into the same ultra-compact form factor. Built on the SE ceramic heating platform for smooth, consistent distillate vaporization.

The 200mAh USB-C rechargeable battery ensures the device lasts through the full 2.0ml capacity. Aroma Seal technology with an optional anti-leak switch and clog-free dual air vents deliver a reliable, leak-free experience.

<strong>Key Features:</strong>
<ul>
<li>2.0ml capacity — double the standard</li>
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
<li>Capacity: 2.0ml</li>
<li>Heating: SE ceramic element</li>
<li>Battery: 200mAh (USB-C)</li>
<li>Body: Plastic</li>
<li>Activation: Inhale-activated</li>
<li>Dimensions: 63H x 36W x 15D mm</li>
<li>Color: Black</li>
</ul>',
        'short_desc'  => 'CCELL Mini Tank 2.0 with 2.0ml capacity, SE ceramic heating, 200mAh USB-C battery, Aroma Seal, and clog-free dual vents. Ultra-compact at 63mm.',
        'meta_desc'   => 'CCELL Mini Tank 2.0 2.0ml all-in-one with SE ceramic heating and 200mAh USB-C battery. Double capacity, ultra-compact. Wholesale from Hamilton Devices.',
    ]),
    'categories' => [$tt_disposable, $tt_ccell, $tt_aio_se],
    'hero_url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2024/09/1.0_Jupiter_Products_MiniTank-2mL.jpg',
    'hero_file'  => 'ccell-mini-tank-2ml-hero.jpg',
    'hero_title' => 'CCELL Mini Tank 2.0 2.0ml',
    'gallery'    => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-01.jpg', 'ccell-mini-tank-gallery-01.jpg', 'CCELL Mini Tank Front'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-02.jpg', 'ccell-mini-tank-gallery-02.jpg', 'CCELL Mini Tank Side'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-03.jpg', 'ccell-mini-tank-gallery-03.jpg', 'CCELL Mini Tank Angle'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-04.jpg', 'ccell-mini-tank-gallery-04.jpg', 'CCELL Mini Tank Back'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-05.jpg', 'ccell-mini-tank-gallery-05.jpg', 'CCELL Mini Tank USB-C'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-07.jpg', 'ccell-mini-tank-gallery-07.jpg', 'CCELL Mini Tank Top'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-08.jpg', 'ccell-mini-tank-gallery-08.jpg', 'CCELL Mini Tank Detail'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-09.jpg', 'ccell-mini-tank-gallery-09.jpg', 'CCELL Mini Tank Overview'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/10_Jupiter_CCELL_AIO_MiniTank_Size.jpg', 'ccell-mini-tank-gallery-size.jpg', 'CCELL Mini Tank Size Comparison'],
    ],
];

// ────────────────────────────────────────────────────────────────────────
// 2c. CCELL Mini Tank EM — 1.0ml AIO (black, EVO MAX)
// ────────────────────────────────────────────────────────────────────────

$products[] = [
    'data' => array_merge($AIO_PRICING, [
        'title'       => 'CCELL Mini Tank EM — 1.0ml All-In-One | EVO MAX',
        'slug'        => 'ccell-mini-tank-em-1-0ml-aio',
        'sku'         => 'MINITANK-EM-10',
        'description' => 'The CCELL Mini Tank EM is the EVOMAX-powered version of the Mini Tank platform. The oversized EVOMAX ceramic heating element with thicker walls delivers superior heat distribution and enhanced vapor production across all oil types — from distillates to live rosins and liquid diamonds.

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
</ul>',
        'short_desc'  => 'CCELL Mini Tank EM 1.0ml AIO with EVOMAX ceramic heating, 200mAh USB-C battery, Aroma Seal, and clog-free dual vents. All oil types.',
        'meta_desc'   => 'CCELL Mini Tank EM 1.0ml all-in-one with EVOMAX ceramic heating and 200mAh USB-C battery. All oil types. Wholesale from Hamilton Devices.',
    ]),
    'categories' => [$tt_disposable, $tt_ccell, $tt_aio_evomax],
    'hero_url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2024/12/01_Jupiter_CCELL_AIO_MiniTank_NoAC.jpg',
    'hero_file'  => 'ccell-mini-tank-em-1ml-hero.jpg',
    'hero_title' => 'CCELL Mini Tank EM 1.0ml',
    'gallery'    => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-01.jpg', 'ccell-mini-tank-gallery-01.jpg', 'CCELL Mini Tank Front'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-02.jpg', 'ccell-mini-tank-gallery-02.jpg', 'CCELL Mini Tank Side'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-03.jpg', 'ccell-mini-tank-gallery-03.jpg', 'CCELL Mini Tank Angle'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-04.jpg', 'ccell-mini-tank-gallery-04.jpg', 'CCELL Mini Tank Back'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-05.jpg', 'ccell-mini-tank-gallery-05.jpg', 'CCELL Mini Tank USB-C'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-07.jpg', 'ccell-mini-tank-gallery-07.jpg', 'CCELL Mini Tank Top'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-08.jpg', 'ccell-mini-tank-gallery-08.jpg', 'CCELL Mini Tank Detail'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_MiniTank_2mL-09.jpg', 'ccell-mini-tank-gallery-09.jpg', 'CCELL Mini Tank Overview'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/10_Jupiter_CCELL_AIO_MiniTank_Size.jpg', 'ccell-mini-tank-gallery-size.jpg', 'CCELL Mini Tank Size Comparison'],
    ],
];

// ────────────────────────────────────────────────────────────────────────
// 2d. CCELL Rosin Bar — 1.0ml AIO (HeRo platform)
// ────────────────────────────────────────────────────────────────────────

$products[] = [
    'data' => array_merge($AIO_PRICING, [
        'title'       => 'CCELL Rosin Bar — 1.0ml All-In-One | HeRo Platform',
        'slug'        => 'ccell-rosin-bar-1-0ml-aio',
        'sku'         => 'ROSINBAR-10-HR',
        'description' => 'The CCELL Rosin Bar 1.0ml is the full-gram version of the Rosin Bar, purpose-built for live rosin formulations using the HeRo heating platform. The HeRo system features partitioned atomization — THC and terpenes are heated at optimal temperatures in separate zones via multi-level heating distribution, preserving authentic strain flavors while maximizing potency.

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
        'short_desc'  => 'CCELL Rosin Bar 1.0ml AIO with HeRo partitioned atomization for live rosin. Dual oil pathways, 200mAh USB-C, isolated airway. ETP body.',
        'meta_desc'   => 'CCELL Rosin Bar 1.0ml all-in-one with HeRo partitioned atomization for live rosin. Dual oil pathways, clog-free. Wholesale from Hamilton Devices.',
    ]),
    'categories' => [$tt_disposable, $tt_ccell, $tt_aio_hero],
    'hero_url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2024/09/1.0_Jupiter_Products_RosinBar-scaled.jpg',
    'hero_file'  => 'ccell-rosin-bar-1ml-hero.jpg',
    'hero_title' => 'CCELL Rosin Bar 1.0ml',
    'gallery'    => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/01_Jupiter_CCELL_AIO_RosinBar_Spin.jpg', 'ccell-rosin-bar-spin-01.jpg', 'CCELL Rosin Bar Front'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/02_Jupiter_CCELL_AIO_RosinBar_Spin.jpg', 'ccell-rosin-bar-spin-02.jpg', 'CCELL Rosin Bar Side'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/03_Jupiter_CCELL_AIO_RosinBar_Spin.jpg', 'ccell-rosin-bar-spin-03.jpg', 'CCELL Rosin Bar Angle'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/04_Jupiter_CCELL_AIO_RosinBar_Spin.jpg', 'ccell-rosin-bar-spin-04.jpg', 'CCELL Rosin Bar Back'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/05_Jupiter_CCELL_AIO_RosinBar_Spin.jpg', 'ccell-rosin-bar-spin-05.jpg', 'CCELL Rosin Bar Detail'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/06_Jupiter_CCELL_AIO_RosinBar_Spin.jpg', 'ccell-rosin-bar-spin-06.jpg', 'CCELL Rosin Bar USB-C'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/07_Jupiter_CCELL_AIO_RosinBar_Spin.jpg', 'ccell-rosin-bar-spin-07.jpg', 'CCELL Rosin Bar Top'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/08_Jupiter_CCELL_AIO_RosinBar_Spin.jpg', 'ccell-rosin-bar-spin-08.jpg', 'CCELL Rosin Bar Bottom'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/09_Jupiter_CCELL_AIO_RosinBar_Spin.jpg', 'ccell-rosin-bar-spin-09.jpg', 'CCELL Rosin Bar Overview'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/10_Jupiter_CCELL_AIO_RosinBar_SpinSize.jpg', 'ccell-rosin-bar-spin-size.jpg', 'CCELL Rosin Bar Size Comparison'],
    ],
];

// ────────────────────────────────────────────────────────────────────────
// 2e. CCELL Bravo — AIO (TBC placeholder)
// ────────────────────────────────────────────────────────────────────────

$products[] = [
    'data' => array_merge($AIO_PRICING, [
        'title'       => 'CCELL Bravo — All-In-One (Coming Soon)',
        'slug'        => 'ccell-bravo-aio',
        'sku'         => 'BRAVO-AIO-TBC',
        'description' => '<strong>Coming Soon</strong>

The CCELL Bravo is an upcoming all-in-one disposable device. Full specifications, images, and pricing will be updated once confirmed by CCELL/Jupiter.

Contact Hamilton Devices for pre-order availability and estimated delivery timelines.',
        'short_desc'  => 'CCELL Bravo all-in-one disposable. Coming soon — specs and pricing TBC.',
        'meta_desc'   => 'CCELL Bravo all-in-one disposable. Coming soon. Contact Hamilton Devices for wholesale pre-order availability.',
    ]),
    'categories' => [$tt_disposable, $tt_ccell],
    'hero_url'   => null,
    'hero_file'  => null,
    'hero_title' => null,
    'gallery'    => [],
];

// ── Create products + download images ──

$created_ids = [];
$all_affected_tt = array_filter([$tt_disposable, $tt_ccell, $tt_aio_se, $tt_aio_evomax, $tt_aio_hero, $tt_ccell_se, $tt_cartridge]);

foreach ($products as $product) {
    $p = $product['data'];
    $categories = $product['categories'];

    echo "Creating: {$p['title']}...\n";
    $post_id = create_product($conn, $p, $categories, $tt_simple);

    if (!$post_id) continue;

    echo "  -> Created (ID: $post_id)\n";
    $created_ids[] = $post_id;

    // Download hero image
    if ($product['hero_url']) {
        echo "  Downloading hero image... ";
        $hero_id = download_image($conn, $product['hero_url'], $product['hero_file'], $product['hero_title'], $post_id, $upload_dir, $year_month);
        if ($hero_id) {
            echo "OK (ID: $hero_id)\n";
            set_thumbnail($conn, $post_id, $hero_id);
        } else {
            echo "FAILED\n";
        }
    }

    // Download gallery images
    if (!empty($product['gallery'])) {
        $gallery_ids = [];
        foreach ($product['gallery'] as $g) {
            echo "  Downloading: {$g[1]}... ";
            $gid = download_image($conn, $g[0], $g[1], $g[2], $post_id, $upload_dir, $year_month);
            if ($gid) {
                echo "OK (ID: $gid)\n";
                $gallery_ids[] = $gid;
            } else {
                echo "FAILED\n";
            }
        }
        set_gallery($conn, $post_id, $gallery_ids);
        echo "  Gallery: " . count($gallery_ids) . " images set\n";
    }
}

// =============================================================================
// PART 3: Update term counts
// =============================================================================

echo "\n══ PART 3: Update Term Counts ══\n\n";

foreach ($all_affected_tt as $tt_id) {
    $conn->query("UPDATE wp_term_taxonomy SET count = (SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = $tt_id) WHERE term_taxonomy_id = $tt_id");
    $r = $conn->query("SELECT t.name, tt.count FROM wp_term_taxonomy tt JOIN wp_terms t ON t.term_id=tt.term_id WHERE tt.term_taxonomy_id=$tt_id");
    $row = $r->fetch_assoc();
    if ($row) echo "  {$row['name']}: {$row['count']} products\n";
}

// =============================================================================
// Summary
// =============================================================================

echo "\n=== Summary ===\n";
echo "Part 1:\n";
echo "  - Created ccell-se subcategory (tt_id=$tt_ccell_se)\n";
echo "  - Fixed 221042 (TH210-SE White Ceramic) — description + Yoast\n";
echo "  - Fixed 221044 (TH210-SE Black Ceramic) — description + Yoast\n";
echo "  - Assigned " . count($se_product_ids) . " SE screw-on variants to ccell-se\n";
echo "Part 2:\n";
echo "  - Created " . count($created_ids) . " new products\n";
if ($created_ids) echo "  - New IDs: " . implode(', ', $created_ids) . "\n";
echo "\nWARNING: All pricing is PLACEHOLDER.\n";
echo "Done!\n";

$conn->close();
