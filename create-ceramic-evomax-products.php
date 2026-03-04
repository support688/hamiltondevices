<?php
/**
 * Create Ceramic EVOMAX cartridge products & fix Kera description
 *
 * The Ceramic EVOMAX is a SEPARATE product from the Kera:
 *   - Ceramic EVOMAX: 1.7Ω, EVOMAX oversized element, 0.5/1.0/2.0ml, snap-fit
 *   - Kera: 1.4Ω, proprietary ceramic element, 0.5/1.0ml only, snap-fit (hand-closable)
 *
 * Run via: docker compose exec -T wordpress php < create-ceramic-evomax-products.php
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Create Ceramic EVOMAX Products & Fix Kera ===\n\n";

// =============================================================================
// Helpers (same pattern as other scripts)
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

// =============================================================================
// Category TT IDs
// =============================================================================

$tt_cartridge = get_tt_id_by_term_id($conn, 543);
$tt_ccell     = get_tt_id_by_term_id($conn, 1234);
$tt_ceramic   = get_tt_id_by_slug($conn, 'ccell-ceramic-evo-max');
$tt_simple    = get_tt_id_by_term_id($conn, 2, 'product_type');

$year_month = date('Y/m');
$upload_dir = "/var/www/html/wp-content/uploads/$year_month";
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// =============================================================================
// STEP 1: Fix Kera description — NOT EVOMAX, uses its own ceramic element at 1.4Ω
// =============================================================================

echo "── Step 1: Fix Kera description ──\n";

$kera_id = 171374;
$kera_desc = 'The CCELL Kera is a full-ceramic cartridge with a proprietary CCELL ceramic heating element optimized for the all-ceramic construction. The entire oil path — body, mouthpiece, center post, and heating element — is ceramic, eliminating all metal contact with oil for the purest possible flavor profile.

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
</ul>';

$kera_short = 'CCELL Kera full-ceramic cartridge with proprietary ceramic heating. Zero metal oil contact, zirconia center post, hand-closable snap-fit. 1.4 ohm, 510 thread.';
$kera_meta = 'CCELL Kera full-ceramic cartridge. Zero metal oil contact, ceramic airway, hand-closable snap-fit. 0.5ml & 1.0ml. Wholesale from Hamilton Devices.';

$now = date('Y-m-d H:i:s');
$now_gmt = gmdate('Y-m-d H:i:s');

$stmt = $conn->prepare("UPDATE wp_posts SET post_content=?, post_excerpt=?, post_modified=?, post_modified_gmt=? WHERE ID=?");
$stmt->bind_param('ssssi', $kera_desc, $kera_short, $now, $now_gmt, $kera_id);
$stmt->execute();

$stmt2 = $conn->prepare("UPDATE wp_postmeta SET meta_value=? WHERE post_id=? AND meta_key='_yoast_wpseo_metadesc'");
$stmt2->bind_param('si', $kera_meta, $kera_id);
$stmt2->execute();

echo "  Fixed Kera (ID: $kera_id) — proprietary ceramic 1.4Ω (NOT EVOMAX)\n";

// =============================================================================
// STEP 2: Create Ceramic EVOMAX cartridge products
// =============================================================================

echo "\n── Step 2: Creating Ceramic EVOMAX cartridge products ──\n\n";

$CERAMIC_PRICING = [
    'price'=>'5.99','lowest'=>'2.99','wholesale_price'=>'3.49',
    'retail_tiers'=>[
        ['range'=>'1-19','price'=>'$5.99'],['range'=>'20-49','price'=>'$4.99'],
        ['range'=>'50-99','price'=>'$3.99'],['range'=>'100-1,999','price'=>'$3.49'],
        ['range'=>'2,000+','price'=>'$2.99'],
    ],
    'wholesale_tiers'=>[
        ['start'=>'100','end'=>'1999','price'=>'3.49'],
        ['start'=>'2000','end'=>'','price'=>'2.99'],
    ],
];

$products = [
    // ── Ceramic EVOMAX 0.5ml ──
    array_merge($CERAMIC_PRICING, [
        'title'       => 'CCELL Ceramic EVOMAX — 0.5ml All-Ceramic Cartridge',
        'slug'        => 'ccell-ceramic-evomax-0-5ml-cartridge',
        'sku'         => 'CER-EMX-05',
        'description' => 'The CCELL Ceramic EVOMAX is an all-ceramic 510-thread cartridge featuring the EVOMAX oversized ceramic heating element with thicker walls for superior heat distribution. The full ceramic body and ceramic airway eliminate all metal contact with oil, delivering the purest flavor from any formulation.

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
</ul>',
        'short_desc'  => 'CCELL Ceramic EVOMAX 0.5ml all-ceramic cartridge with EVOMAX oversized heating. Zero metal oil contact, ceramic airway. 1.7 ohm, 510 thread.',
        'meta_desc'   => 'CCELL Ceramic EVOMAX 0.5ml all-ceramic cartridge with EVOMAX heating. Zero metal contact, ceramic airway. Wholesale from Hamilton Devices.',
    ]),

    // ── Ceramic EVOMAX 1.0ml ──
    array_merge($CERAMIC_PRICING, [
        'title'       => 'CCELL Ceramic EVOMAX — 1.0ml All-Ceramic Cartridge',
        'slug'        => 'ccell-ceramic-evomax-1-0ml-cartridge',
        'sku'         => 'CER-EMX-10',
        'description' => 'The CCELL Ceramic EVOMAX 1.0ml is an all-ceramic 510-thread cartridge featuring the EVOMAX oversized ceramic heating element. The full ceramic body and ceramic airway eliminate all metal contact with oil for the purest possible flavor profile.

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
</ul>',
        'short_desc'  => 'CCELL Ceramic EVOMAX 1.0ml all-ceramic cartridge with EVOMAX oversized heating. Zero metal oil contact, ceramic airway. 1.7 ohm, 510 thread.',
        'meta_desc'   => 'CCELL Ceramic EVOMAX 1.0ml all-ceramic cartridge with EVOMAX heating. Zero metal contact, ceramic airway. Wholesale from Hamilton Devices.',
    ]),

    // ── Ceramic EVOMAX 2.0ml ──
    array_merge($CERAMIC_PRICING, [
        'title'       => 'CCELL Ceramic EVOMAX — 2.0ml All-Ceramic Cartridge',
        'slug'        => 'ccell-ceramic-evomax-2-0ml-cartridge',
        'sku'         => 'CER-EMX-20',
        'description' => 'The CCELL Ceramic EVOMAX 2.0ml is the largest all-ceramic 510-thread cartridge in the CCELL lineup. Featuring the EVOMAX oversized ceramic heating element with full ceramic body and ceramic airway — zero metal touches your oil from fill port to mouthpiece.

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
</ul>',
        'short_desc'  => 'CCELL Ceramic EVOMAX 2.0ml all-ceramic cartridge with EVOMAX oversized heating. Largest ceramic cartridge — zero metal oil contact. 1.7 ohm.',
        'meta_desc'   => 'CCELL Ceramic EVOMAX 2.0ml all-ceramic cartridge with EVOMAX heating. Zero metal contact. Wholesale from Hamilton Devices.',
    ]),
];

$categories = [$tt_cartridge, $tt_ccell, $tt_ceramic];
$created_ids = [];

foreach ($products as $p) {
    echo "Creating: {$p['title']}...\n";
    $post_id = create_product($conn, $p, $categories, $tt_simple);
    if ($post_id) {
        echo "  -> Created (ID: $post_id)\n";
        $created_ids[] = $post_id;
    }
}

// =============================================================================
// STEP 3: Download images from Jupiter
// =============================================================================

echo "\n── Step 3: Downloading images ──\n";

// Hero image — shared across all 3 variants
$hero_url = 'https://www.jupiterresearch.com/wp-content/uploads/2024/09/1.0_Jupiter_Products_EVOMAX-Ceramic-1.jpg';

// Gallery images
$gallery_urls = [
    ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/01_Jupiter_CCELL_EVOMAX_Ceramic_10mL.jpg', 'ccell-ceramic-evomax-gallery-1ml.jpg', 'CCELL Ceramic EVOMAX 1.0ml'],
    ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/02_Jupiter_CCELL_EVOMAX_Ceramic_05mL.jpg', 'ccell-ceramic-evomax-gallery-05ml.jpg', 'CCELL Ceramic EVOMAX 0.5ml'],
    ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/03_Jupiter_CCELL_EVOMAX_Ceramic_Size.jpg', 'ccell-ceramic-evomax-gallery-size.jpg', 'CCELL Ceramic EVOMAX Size Comparison'],
];

$hero_files = [
    'ccell-ceramic-evomax-05ml.jpg',
    'ccell-ceramic-evomax-10ml.jpg',
    'ccell-ceramic-evomax-20ml.jpg',
];

foreach ($created_ids as $i => $pid) {
    // Download hero image (or reuse if already downloaded)
    $hero_file = $hero_files[$i] ?? $hero_files[0];
    echo "  Downloading hero for product $pid... ";
    $hero_id = download_image($conn, $hero_url, $hero_file, "CCELL Ceramic EVOMAX Cartridge", $pid, $upload_dir, $year_month);
    if ($hero_id) {
        echo "OK (ID: $hero_id)\n";
        // Set as thumbnail
        $existing = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=$pid AND meta_key='_thumbnail_id' LIMIT 1");
        if ($existing->num_rows > 0) {
            $conn->query("UPDATE wp_postmeta SET meta_value='$hero_id' WHERE post_id=$pid AND meta_key='_thumbnail_id'");
        } else {
            $conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($pid, '_thumbnail_id', '$hero_id')");
        }
    } else {
        echo "FAILED\n";
    }

    // Download gallery images
    $gallery_ids = [];
    foreach ($gallery_urls as $g) {
        echo "  Downloading gallery: {$g[1]}... ";
        $gid = download_image($conn, $g[0], $g[1], $g[2], $pid, $upload_dir, $year_month);
        if ($gid) {
            echo "OK (ID: $gid)\n";
            $gallery_ids[] = $gid;
        } else {
            echo "FAILED\n";
        }
    }

    if (!empty($gallery_ids)) {
        $gallery_str = implode(',', $gallery_ids);
        $conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($pid, '_product_image_gallery', '$gallery_str')");
        echo "  Gallery set: " . count($gallery_ids) . " images\n";
    }
}

// =============================================================================
// STEP 4: Update term counts
// =============================================================================

echo "\n── Step 4: Updating term counts ──\n";

$all_tt = array_filter([$tt_cartridge, $tt_ccell, $tt_ceramic]);
foreach ($all_tt as $tt_id) {
    $conn->query("UPDATE wp_term_taxonomy SET count = (SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = $tt_id) WHERE term_taxonomy_id = $tt_id");
    $r = $conn->query("SELECT t.name, tt.count FROM wp_term_taxonomy tt JOIN wp_terms t ON t.term_id=tt.term_id WHERE tt.term_taxonomy_id=$tt_id");
    $row = $r->fetch_assoc();
    if ($row) echo "  {$row['name']}: {$row['count']} products\n";
}

echo "\n=== Complete ===\n";
echo "Kera fixed: proprietary ceramic 1.4Ω (not EVOMAX)\n";
echo "Created: " . count($created_ids) . " Ceramic EVOMAX cartridge products\n";
if ($created_ids) echo "New IDs: " . implode(', ', $created_ids) . "\n";
echo "WARNING: All pricing is PLACEHOLDER.\n";
echo "Done!\n";

$conn->close();
