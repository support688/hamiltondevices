<?php
/**
 * Fix Easy Cart category page:
 * 1. Remove 5 non-Easy products from ccell-easy category
 * 2. Add M6T05-SE to ccell-se subcategory
 * 3. Create new M6T05-Easy product (0.5ml ETP snap-fit)
 * 4. Update term counts
 *
 * Run via: docker compose exec -T wordpress php < fix-easy-cart-category.php
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Fix Easy Cart Category ===\n\n";

// ── Category IDs ──
$EASY_CART_TT  = 1372;  // CCELL Easy Cart
$CCELL_SE_TT   = 1381;  // CCELL SE Glass
$CARTRIDGES_TT = 543;   // Cartridges parent
$CCELL_TT      = 1234;  // ccell brand
$SIMPLE_TT     = 2;     // product_type = simple

// ═══════════════════════════════════════════════════════════
// Part 1: Remove 5 non-Easy products from Easy Cart category
// ═══════════════════════════════════════════════════════════

echo "── Removing non-Easy products from Easy Cart ──\n";

$non_easy_ids = [221066, 221070, 221062, 221071, 230111];
$ids_str = implode(',', $non_easy_ids);

$result = $conn->query("SELECT tr.object_id, p.post_title
    FROM wp_term_relationships tr
    JOIN wp_posts p ON tr.object_id = p.ID
    WHERE tr.object_id IN ($ids_str) AND tr.term_taxonomy_id = $EASY_CART_TT");

while ($row = $result->fetch_assoc()) {
    echo "  Removing: {$row['post_title']} (ID: {$row['object_id']})\n";
}

$conn->query("DELETE FROM wp_term_relationships WHERE object_id IN ($ids_str) AND term_taxonomy_id = $EASY_CART_TT");
echo "  Removed " . $conn->affected_rows . " products from Easy Cart\n";

// ═══════════════════════════════════════════════════════════
// Part 2: Add M6T05-SE (230111) to ccell-se subcategory
// ═══════════════════════════════════════════════════════════

echo "\n── Adding M6T05-SE to CCELL SE subcategory ──\n";
$conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES (230111, $CCELL_SE_TT, 0)");
if ($conn->affected_rows > 0) {
    echo "  Added M6T05-SE (230111) to ccell-se\n";
} else {
    echo "  M6T05-SE (230111) already in ccell-se\n";
}

// ═══════════════════════════════════════════════════════════
// Part 3: Create M6T05-Easy product
// ═══════════════════════════════════════════════════════════

echo "\n── Creating M6T05-Easy product ──\n";

// Check if it already exists
$check = $conn->query("SELECT ID FROM wp_posts WHERE post_title LIKE '%M6T05-Easy%' AND post_type='product' AND post_status='publish' LIMIT 1");
if ($check->num_rows > 0) {
    $existing = $check->fetch_assoc();
    echo "  Already exists: ID {$existing['ID']}\n";
    $product_id = $existing['ID'];
} else {
    $title = 'CCELL M6T05-Easy - 0.5ML Poly Cartridge with Snap-Fit Mouthpiece';
    $slug = 'ccell-m6t05-easy-0-5ml-poly-cartridge-snap-fit';
    $content = 'The CCELL M6T05-Easy is a 0.5ml ETP (Engineering ThermoPlastic) cartridge from the Essential Series featuring the SE Atomizer Platform. The durable BPA-free ETP tank is ideal for high-volume programs prioritizing durability and cost-effectiveness.

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
</ul>';
    $excerpt = 'CCELL M6T05-Easy 0.5ml ETP cartridge with SE ceramic heating. Best value half-gram ETP cartridge.';

    $now = date('Y-m-d H:i:s');
    $now_gmt = gmdate('Y-m-d H:i:s');
    $guid = "http://localhost:8080/?post_type=product&p=0"; // placeholder

    $stmt = $conn->prepare("INSERT INTO wp_posts (
        post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
        post_status, comment_status, ping_status, post_password, post_name,
        to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered,
        post_parent, guid, menu_order, post_type, post_mime_type, comment_count
    ) VALUES (
        1, ?, ?, ?, ?, ?,
        'publish', 'open', 'closed', '', ?,
        '', '', ?, ?, '',
        0, ?, 0, 'product', '', 0
    )");
    $stmt->bind_param('sssssssss', $now, $now_gmt, $content, $title, $excerpt, $slug, $now, $now_gmt, $guid);
    $stmt->execute();
    $product_id = $conn->insert_id;

    if (!$product_id) {
        die("  FAILED to create product\n");
    }

    // Update GUID
    $conn->query("UPDATE wp_posts SET guid='http://localhost:8080/?post_type=product&p=$product_id' WHERE ID=$product_id");

    echo "  Created product: ID $product_id\n";

    // ── Meta data ──
    $meta = [
        '_sku' => 'M6T05-EASY-SF',
        '_regular_price' => '3.49',
        '_price' => '3.49',
        '_stock_status' => 'instock',
        '_manage_stock' => 'no',
        '_stock' => '',
        '_visibility' => 'visible',
        '_virtual' => 'no',
        '_downloadable' => 'no',
        '_sold_individually' => 'no',
        '_backorders' => 'no',
        '_wc_average_rating' => '0',
        '_wc_review_count' => '0',
        'total_sales' => '0',

        // Retail pricing tiers
        'price_text' => 'As low as',
        'lowest_price' => '1.69',
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

        // Wholesale pricing
        'wholesale_customer_have_wholesale_price' => 'yes',
        'wholesale_customer_wholesale_price' => '2.09',
        'wholesale_customer_wholesale_minimum_order_quantity' => '100',
        'wholesale_customer_wholesale_order_quantity_step' => '100',
        'wprice_text' => 'As low as',
        'wlowest_price' => '1.69',
        'wquantity_limit_1' => '100–1,999',
        'wprice_per_unit_1' => '$2.09',
        'wquantity_limit_2' => '2,000+',
        'wprice_per_unit_2' => '$1.69',

        // Wholesale plugin settings
        'wwpp_ignore_cat_level_wholesale_discount' => 'no',
        'wwpp_ignore_role_level_wholesale_discount' => 'no',
        'wwpp_post_meta_enable_quantity_discount_rule' => 'yes',
        'wwpp_post_meta_quantity_discount_rule_mapping' => serialize([
            ['wholesale_role'=>'wholesale_customer','start_qty'=>'100','end_qty'=>'1999','price_type'=>'fixed-price','wholesale_price'=>'2.09'],
            ['wholesale_role'=>'wholesale_customer','start_qty'=>'2000','end_qty'=>'','price_type'=>'fixed-price','wholesale_price'=>'1.69'],
        ]),
        'wwpp_product_wholesale_visibility_filter' => 'all',

        // Bulk discount (enabled but tiers handled by custom fields above)
        '_bulkdiscount_enabled' => 'yes',

        // Yoast SEO
        '_yoast_wpseo_metadesc' => 'CCELL M6T05-Easy 0.5ml ETP cartridge with SE ceramic. Wholesale from Hamilton Devices.',

        // ACF field references (match M6T05-S pattern)
        '_lowest_price' => 'field_5c694da491f89',
        '_price_text' => 'field_5c695785825e5',
        '_quantity_limit_1' => 'field_5c6a24623baa2',
        '_price_per_unit_1' => 'field_5c6a24a8db699',
        '_quantity_limit_2' => 'field_5c6a24dd9df94',
        '_price_per_unit_2' => 'field_5c6a24ed9df97',
        '_quantity_limit_3' => 'field_5c6a24e39df95',
        '_price_per_unit_3' => 'field_5c6a24ef9df98',
        '_quantity_limit_4' => 'field_5cb8aa2519383',
        '_price_per_unit_4' => 'field_5cfe8826e1be2',
        '_quantity_limit_5' => 'field_66ec46ccbf00e',
        '_price_per_unit_5' => 'field_66ec46d8bf00f',
        '_wlowest_price' => 'field_61bc1cafe7dda',
        '_wprice_text' => 'field_61bc1cabe7dd9',
        '_wquantity_limit_1' => 'field_61b957c9f9a14',
        '_wprice_per_unit_1' => 'field_61b957d9f9a15',
        '_wquantity_limit_2' => 'field_61b957e1f9a16',
        '_wprice_per_unit_2' => 'field_61b957edf9a17',
        '_msrpprice' => 'field_61b9580ef9a19',
    ];

    $stmt_meta = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)");
    foreach ($meta as $key => $value) {
        $stmt_meta->bind_param('iss', $product_id, $key, $value);
        $stmt_meta->execute();
    }
    echo "  Added " . count($meta) . " meta fields\n";

    // ── Categories ──
    $categories = [$CARTRIDGES_TT, $CCELL_TT, $EASY_CART_TT, $SIMPLE_TT];
    foreach ($categories as $tt_id) {
        $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($product_id, $tt_id, 0)");
    }
    echo "  Assigned to Cartridges, ccell, Easy Cart, product_type=simple\n";
}

// ═══════════════════════════════════════════════════════════
// Part 4: Download images for M6T05-Easy
// ═══════════════════════════════════════════════════════════

echo "\n── Downloading M6T05-Easy images ──\n";

$year_month = '2026/03';
$upload_dir = "/var/www/html/wp-content/uploads/$year_month";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

function download_image($url, $local_path) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code == 200 && $data) {
        file_put_contents($local_path, $data);
        return true;
    }
    return false;
}

function create_attachment($conn, $filename, $title, $product_id, $upload_dir, $year_month) {
    $local_path = "$upload_dir/$filename";
    $relative_path = "$year_month/$filename";

    if (!file_exists($local_path)) {
        echo "  NOT FOUND: $local_path\n";
        return false;
    }

    // Check if attachment already exists
    $check = $conn->prepare("SELECT p.ID FROM wp_posts p JOIN wp_postmeta pm ON p.ID=pm.post_id WHERE pm.meta_key='_wp_attached_file' AND pm.meta_value=? AND p.post_type='attachment' LIMIT 1");
    $check->bind_param('s', $relative_path);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    if ($row) return $row['ID'];

    $img_info = @getimagesize($local_path);
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

// Download 0.5ml images from Jupiter
$images = [
    ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2025/09/Jupiter_EasyCarts_ETP_0.5mL_02.jpg', 'file' => 'ccell-m6t05-easy-05ml-01.jpg', 'title' => 'CCELL M6T05-Easy 0.5ML 1'],
    ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2025/09/Jupiter_EasyCarts_ETP_0.5mL_03.jpg', 'file' => 'ccell-m6t05-easy-05ml-02.jpg', 'title' => 'CCELL M6T05-Easy 0.5ML 2'],
    ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2025/09/Jupiter_EasyCarts_ETP_0.5mL_04.jpg', 'file' => 'ccell-m6t05-easy-05ml-03.jpg', 'title' => 'CCELL M6T05-Easy 0.5ML 3'],
    ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2025/09/Jupiter_EasyCarts_ETP_0.5mL_05.jpg', 'file' => 'ccell-m6t05-easy-05ml-04.jpg', 'title' => 'CCELL M6T05-Easy 0.5ML 4'],
    ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2025/09/Jupiter_EasyCarts_ETP_051mL_06.jpg', 'file' => 'ccell-m6t05-easy-05ml-05.jpg', 'title' => 'CCELL M6T05-Easy 0.5ML 5'],
];

$attach_ids = [];
foreach ($images as $i => $img) {
    $local = "$upload_dir/{$img['file']}";
    echo "  Downloading {$img['file']}... ";

    if (file_exists($local)) {
        echo "(already exists) ";
    } else {
        if (!download_image($img['url'], $local)) {
            echo "DOWNLOAD FAILED\n";
            continue;
        }
    }

    $id = create_attachment($conn, $img['file'], $img['title'], $product_id, $upload_dir, $year_month);
    if ($id) {
        echo "OK (ID: $id)\n";
        $attach_ids[] = $id;
    } else {
        echo "ATTACH FAILED\n";
    }
}

// Set hero and gallery
if (!empty($attach_ids)) {
    $hero_id = $attach_ids[0];

    // Check if thumbnail already set
    $existing = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=$product_id AND meta_key='_thumbnail_id' LIMIT 1");
    if ($existing->num_rows > 0) {
        $conn->query("UPDATE wp_postmeta SET meta_value='$hero_id' WHERE post_id=$product_id AND meta_key='_thumbnail_id'");
    } else {
        $conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($product_id, '_thumbnail_id', '$hero_id')");
    }
    echo "  Hero image set: ID $hero_id\n";

    // Gallery = all except hero
    $gallery_ids = array_slice($attach_ids, 1);
    if (!empty($gallery_ids)) {
        $gallery_str = implode(',', $gallery_ids);
        $existing = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=$product_id AND meta_key='_product_image_gallery' LIMIT 1");
        if ($existing->num_rows > 0) {
            $conn->query("UPDATE wp_postmeta SET meta_value='$gallery_str' WHERE post_id=$product_id AND meta_key='_product_image_gallery'");
        } else {
            $conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($product_id, '_product_image_gallery', '$gallery_str')");
        }
        echo "  Gallery set: " . count($gallery_ids) . " images\n";
    }
}

// ═══════════════════════════════════════════════════════════
// Part 5: Update term counts
// ═══════════════════════════════════════════════════════════

echo "\n── Updating term counts ──\n";

$terms_to_update = [$EASY_CART_TT, $CCELL_SE_TT, $CARTRIDGES_TT, $CCELL_TT];
foreach ($terms_to_update as $tt_id) {
    $count_result = $conn->query("SELECT COUNT(*) as cnt FROM wp_term_relationships tr JOIN wp_posts p ON tr.object_id = p.ID WHERE tr.term_taxonomy_id = $tt_id AND p.post_type = 'product' AND p.post_status = 'publish'");
    $count = $count_result->fetch_assoc()['cnt'];
    $conn->query("UPDATE wp_term_taxonomy SET count = $count WHERE term_taxonomy_id = $tt_id");

    $name_result = $conn->query("SELECT t.name FROM wp_terms t JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id WHERE tt.term_taxonomy_id = $tt_id");
    $name = $name_result->fetch_assoc()['name'];
    echo "  $name (tt_id=$tt_id): count = $count\n";
}

// ═══════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════

echo "\n── Easy Cart products now ──\n";
$result = $conn->query("SELECT p.ID, p.post_title FROM wp_term_relationships tr JOIN wp_posts p ON tr.object_id = p.ID WHERE tr.term_taxonomy_id = $EASY_CART_TT AND p.post_type = 'product' AND p.post_status = 'publish' ORDER BY p.post_title");
while ($row = $result->fetch_assoc()) {
    echo "  {$row['ID']}: {$row['post_title']}\n";
}

echo "\n=== Done ===\n";

$conn->close();
