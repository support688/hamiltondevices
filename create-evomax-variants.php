<?php
/**
 * Create EVO MAX cartridge variant listings
 *
 * TH210 EVO MAX (1.0ml Glass): Black Ceramic, White Plastic, Black Plastic, Clear Plastic
 * TH205 EVO MAX (0.5ml Glass): White Ceramic, Black Ceramic, White Plastic, Black Plastic, Clear Plastic
 * M6T10 EVO MAX (1.0ml Poly):  Clear Plastic, White Plastic, Black Plastic
 * M6T05 EVO MAX (0.5ml Poly):  Clear Plastic, White Plastic, Black Plastic
 *
 * Run via: docker compose exec -T wordpress php < create-evomax-variants.php
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

echo "=== Create EVO MAX Cartridge Variants ===\n\n";

// ── Constants ──
$CARTRIDGES_TT = 543;
$CCELL_TT      = 1234;
$EVOMAX_TT     = 1373;
$SIMPLE_TT     = 2;

$year_month = '2026/03';
$upload_dir = "/var/www/html/wp-content/uploads/$year_month";
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// ── Helpers ──

function download_image($url, $local_path) {
    if (file_exists($local_path)) return true;
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

function create_attachment($conn, $filename, $title, $parent_id, $upload_dir, $year_month) {
    $local_path = "$upload_dir/$filename";
    $relative_path = "$year_month/$filename";

    if (!file_exists($local_path)) return false;

    // Check existing
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
    $stmt->bind_param('ssssssiss', $now, $now_gmt, $title, $slug, $now, $now_gmt, $parent_id, $guid, $mime_type);
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

function create_product($conn, $product, $categories) {
    // Check if SKU already exists
    $check = $conn->prepare("SELECT post_id FROM wp_postmeta WHERE meta_key='_sku' AND meta_value=? LIMIT 1");
    $check->bind_param('s', $product['sku']);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    if ($row) {
        echo "  Already exists (SKU {$product['sku']}): ID {$row['post_id']}\n";
        return $row['post_id'];
    }

    $now = date('Y-m-d H:i:s');
    $now_gmt = gmdate('Y-m-d H:i:s');
    $guid = "http://localhost:8080/?post_type=product&p=0";

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
    $stmt->bind_param('sssssssss', $now, $now_gmt, $product['content'], $product['title'], $product['excerpt'], $product['slug'], $now, $now_gmt, $guid);
    $stmt->execute();
    $pid = $conn->insert_id;
    if (!$pid) { echo "  FAILED to insert product\n"; return false; }

    $conn->query("UPDATE wp_posts SET guid='http://localhost:8080/?post_type=product&p=$pid' WHERE ID=$pid");

    // Meta
    $meta = $product['meta'];
    $stmt_m = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)");
    foreach ($meta as $k => $v) {
        $stmt_m->bind_param('iss', $pid, $k, $v);
        $stmt_m->execute();
    }

    // Categories
    foreach ($categories as $tt) {
        $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($pid, $tt, 0)");
    }

    return $pid;
}

// ═══════════════════════════════════════════════════════════
// Download images from Jupiter
// ═══════════════════════════════════════════════════════════

echo "── Downloading images ──\n";

$img_map = [
    'glass-10ml-hero' => ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2024/12/01_Jupiter_CCELL_EVOMAX_Glass_10mL.jpg', 'file' => 'ccell-evomax-glass-10ml.jpg'],
    'glass-05ml-hero' => ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2024/12/02_Jupiter_CCELL_EVOMAX_Glass_05mL.jpg', 'file' => 'ccell-evomax-glass-05ml.jpg'],
    'glass-size'      => ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2024/12/03_Jupiter_CCELL_EVOMAX_Glass_Size.jpg', 'file' => 'ccell-evomax-glass-size.jpg'],
    'glass-main'      => ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2024/09/1.0_Jupiter_Products_EVOMAX-Glass-scaled.jpg', 'file' => 'ccell-evomax-glass-main.jpg'],
    'etp-10ml-hero'   => ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2024/12/01_Jupiter_CCELL_EVOMAX_ETP_1.0mL.jpg', 'file' => 'ccell-evomax-etp-10ml.jpg'],
    'etp-05ml-hero'   => ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2024/12/02_Jupiter_CCELL_EVOMAX_ETP_0.5mL.jpg', 'file' => 'ccell-evomax-etp-05ml.jpg'],
    'etp-size'        => ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2024/12/03_Jupiter_CCELL_EVOMAX_ETP_Size.jpg', 'file' => 'ccell-evomax-etp-size.jpg'],
    'etp-main'        => ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2024/09/1.0_Jupiter_Products_EVOMAX-ETP-scaled.jpg', 'file' => 'ccell-evomax-etp-main.jpg'],
    'atomizer'        => ['url' => 'https://www.jupiterresearch.com/wp-content/uploads/2024/11/Jupiter_CCELL_Atomizer_EVOMAX_Off.png', 'file' => 'ccell-evomax-atomizer.png'],
];

$attach_cache = [];
foreach ($img_map as $key => $img) {
    $local = "$upload_dir/{$img['file']}";
    echo "  {$img['file']}... ";
    if (download_image($img['url'], $local)) {
        echo "OK\n";
    } else {
        echo "FAILED\n";
    }
}

// Create all attachment records (we'll use product ID 0 as parent, update later)
echo "\n── Creating attachment records ──\n";
foreach ($img_map as $key => $img) {
    $title = 'CCELL EVO MAX ' . str_replace('-', ' ', $key);
    $id = create_attachment($conn, $img['file'], $title, 0, $upload_dir, $year_month);
    if ($id) {
        $attach_cache[$key] = $id;
        echo "  $key: ID $id\n";
    } else {
        echo "  $key: FAILED\n";
    }
}

// ═══════════════════════════════════════════════════════════
// Shared pricing meta template (matches existing TH210 EVO MAX 243006)
// ═══════════════════════════════════════════════════════════

$EVOMAX_PRICING = [
    '_regular_price' => '4.99',
    '_price' => '4.99',
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

    'price_text' => 'As low as',
    'lowest_price' => '2.49',
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

    'wholesale_customer_have_wholesale_price' => 'yes',
    'wholesale_customer_wholesale_price' => '2.99',
    'wholesale_customer_wholesale_minimum_order_quantity' => '100',
    'wholesale_customer_wholesale_order_quantity_step' => '100',
    'wprice_text' => 'As low as',
    'wlowest_price' => '2.49',
    'wquantity_limit_1' => '100–1,999',
    'wprice_per_unit_1' => '$2.99',
    'wquantity_limit_2' => '2,000+',
    'wprice_per_unit_2' => '$2.49',

    'wwpp_ignore_cat_level_wholesale_discount' => 'no',
    'wwpp_ignore_role_level_wholesale_discount' => 'no',
    'wwpp_post_meta_enable_quantity_discount_rule' => 'yes',
    'wwpp_post_meta_quantity_discount_rule_mapping' => serialize([
        ['wholesale_role'=>'wholesale_customer','start_qty'=>'100','end_qty'=>'1999','price_type'=>'fixed-price','wholesale_price'=>'2.99'],
        ['wholesale_role'=>'wholesale_customer','start_qty'=>'2000','end_qty'=>'','price_type'=>'fixed-price','wholesale_price'=>'2.49'],
    ]),
    'wwpp_product_wholesale_visibility_filter' => 'all',

    '_bulkdiscount_enabled' => 'yes',

    // ACF field references
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

// ═══════════════════════════════════════════════════════════
// Description templates
// ═══════════════════════════════════════════════════════════

function glass_content($capacity, $mouthpiece_desc, $mouthpiece_material) {
    $dim_h = $capacity === '1.0' ? '66.1' : '51.8';
    return "The CCELL TH2" . ($capacity === '1.0' ? '10' : '05') . " EVO MAX is a {$capacity}ml glass-body cartridge featuring the advanced EVO MAX oversized ceramic heating element. Engineered to handle the full viscosity spectrum — from thin distillates to thick live rosins and liquid diamonds — the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance from the very first puff.

The {$mouthpiece_desc} provides a {$mouthpiece_material} finish. Combined with the borosilicate glass tank for full oil visibility and a standard 510 thread connection, this cartridge is built for brands that demand top-tier performance across every formulation.

<strong>Key Features:</strong>
<ul>
<li>{$capacity}ml capacity borosilicate glass tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>{$mouthpiece_desc} (screw-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Leak-proof design with 4 × 1.6mm intake holes</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: {$capacity}ml</li>
<li>Body: Borosilicate glass</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: {$mouthpiece_desc} (screw-on)</li>
<li>Dimensions: {$dim_h}H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>";
}

function etp_content($capacity, $mouthpiece_desc, $mouthpiece_material) {
    $dim_h = $capacity === '1.0' ? '62' : '52';
    return "The CCELL M6T" . ($capacity === '1.0' ? '10' : '05') . " EVO MAX is a {$capacity}ml ETP (Engineering ThermoPlastic) cartridge featuring the advanced EVO MAX oversized ceramic heating element. The durable BPA-free ETP tank combined with the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance across the full viscosity spectrum.

The {$mouthpiece_desc} provides a {$mouthpiece_material} finish. Ideal for brands that want EVO MAX performance in a shatter-resistant poly body.

<strong>Key Features:</strong>
<ul>
<li>{$capacity}ml capacity BPA-free ETP tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>{$mouthpiece_desc} (press-on)</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Shatter-resistant body</li>
<li>No break-in period — activates on the first puff</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: {$capacity}ml</li>
<li>Body: BPA-free ETP (Engineering ThermoPlastic)</li>
<li>Coil: EVO MAX oversized ceramic</li>
<li>Resistance: ~1.2&#8486;</li>
<li>Thread: 510</li>
<li>Mouthpiece: {$mouthpiece_desc} (press-on)</li>
<li>Dimensions: {$dim_h}H × 10.5W × 10.5D mm</li>
<li>Intake Holes: 4 × 1.6mm</li>
<li>Oil Compatibility: Distillate, Live Resin, Live Rosin, Liquid Diamonds</li>
</ul>";
}

// ═══════════════════════════════════════════════════════════
// Product definitions
// ═══════════════════════════════════════════════════════════

$products = [
    // ── TH210 EVO MAX (1.0ml Glass) — White Ceramic already exists (243006) ──
    [
        'title' => 'CCELL TH210 EVO MAX — 1.0ml Glass Cartridge | Black Ceramic Mouthpiece',
        'slug' => 'ccell-th210-evo-max-1ml-black-ceramic',
        'sku' => 'TH210-EVOMAX-BC',
        'hero_key' => 'glass-10ml-hero',
        'gallery_keys' => ['glass-main', 'glass-size', 'atomizer'],
        'content' => glass_content('1.0', 'Black ceramic mouthpiece', 'clean, premium'),
        'excerpt' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with black ceramic mouthpiece. Handles distillate through live rosin.',
        'yoast' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with black ceramic mouthpiece. Wholesale from Hamilton Devices.',
    ],
    [
        'title' => 'CCELL TH210 EVO MAX — 1.0ml Glass Cartridge | White Plastic Mouthpiece',
        'slug' => 'ccell-th210-evo-max-1ml-white-plastic',
        'sku' => 'TH210-EVOMAX-WPL',
        'hero_key' => 'glass-10ml-hero',
        'gallery_keys' => ['glass-main', 'glass-size', 'atomizer'],
        'content' => glass_content('1.0', 'White plastic mouthpiece', 'lightweight'),
        'excerpt' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with white plastic mouthpiece. Handles distillate through live rosin.',
        'yoast' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with white plastic mouthpiece. Wholesale from Hamilton Devices.',
    ],
    [
        'title' => 'CCELL TH210 EVO MAX — 1.0ml Glass Cartridge | Black Plastic Mouthpiece',
        'slug' => 'ccell-th210-evo-max-1ml-black-plastic',
        'sku' => 'TH210-EVOMAX-BPL',
        'hero_key' => 'glass-10ml-hero',
        'gallery_keys' => ['glass-main', 'glass-size', 'atomizer'],
        'content' => glass_content('1.0', 'Black plastic mouthpiece', 'lightweight'),
        'excerpt' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with black plastic mouthpiece. Handles distillate through live rosin.',
        'yoast' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with black plastic mouthpiece. Wholesale from Hamilton Devices.',
    ],
    [
        'title' => 'CCELL TH210 EVO MAX — 1.0ml Glass Cartridge | Clear Plastic Mouthpiece',
        'slug' => 'ccell-th210-evo-max-1ml-clear-plastic',
        'sku' => 'TH210-EVOMAX-CPL',
        'hero_key' => 'glass-10ml-hero',
        'gallery_keys' => ['glass-main', 'glass-size', 'atomizer'],
        'content' => glass_content('1.0', 'Clear plastic mouthpiece', 'transparent'),
        'excerpt' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with clear plastic mouthpiece. Handles distillate through live rosin.',
        'yoast' => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with clear plastic mouthpiece. Wholesale from Hamilton Devices.',
    ],

    // ── TH205 EVO MAX (0.5ml Glass) ──
    [
        'title' => 'CCELL TH205 EVO MAX — 0.5ml Glass Cartridge | White Ceramic Mouthpiece',
        'slug' => 'ccell-th205-evo-max-05ml-white-ceramic',
        'sku' => 'TH205-EVOMAX-WC',
        'hero_key' => 'glass-05ml-hero',
        'gallery_keys' => ['glass-main', 'glass-size', 'atomizer'],
        'content' => glass_content('0.5', 'White ceramic mouthpiece', 'clean, premium'),
        'excerpt' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with white ceramic mouthpiece. Handles distillate through live rosin.',
        'yoast' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with white ceramic mouthpiece. Wholesale from Hamilton Devices.',
    ],
    [
        'title' => 'CCELL TH205 EVO MAX — 0.5ml Glass Cartridge | Black Ceramic Mouthpiece',
        'slug' => 'ccell-th205-evo-max-05ml-black-ceramic',
        'sku' => 'TH205-EVOMAX-BC',
        'hero_key' => 'glass-05ml-hero',
        'gallery_keys' => ['glass-main', 'glass-size', 'atomizer'],
        'content' => glass_content('0.5', 'Black ceramic mouthpiece', 'clean, premium'),
        'excerpt' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with black ceramic mouthpiece. Handles distillate through live rosin.',
        'yoast' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with black ceramic mouthpiece. Wholesale from Hamilton Devices.',
    ],
    [
        'title' => 'CCELL TH205 EVO MAX — 0.5ml Glass Cartridge | White Plastic Mouthpiece',
        'slug' => 'ccell-th205-evo-max-05ml-white-plastic',
        'sku' => 'TH205-EVOMAX-WPL',
        'hero_key' => 'glass-05ml-hero',
        'gallery_keys' => ['glass-main', 'glass-size', 'atomizer'],
        'content' => glass_content('0.5', 'White plastic mouthpiece', 'lightweight'),
        'excerpt' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with white plastic mouthpiece. Handles distillate through live rosin.',
        'yoast' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with white plastic mouthpiece. Wholesale from Hamilton Devices.',
    ],
    [
        'title' => 'CCELL TH205 EVO MAX — 0.5ml Glass Cartridge | Black Plastic Mouthpiece',
        'slug' => 'ccell-th205-evo-max-05ml-black-plastic',
        'sku' => 'TH205-EVOMAX-BPL',
        'hero_key' => 'glass-05ml-hero',
        'gallery_keys' => ['glass-main', 'glass-size', 'atomizer'],
        'content' => glass_content('0.5', 'Black plastic mouthpiece', 'lightweight'),
        'excerpt' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with black plastic mouthpiece. Handles distillate through live rosin.',
        'yoast' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with black plastic mouthpiece. Wholesale from Hamilton Devices.',
    ],
    [
        'title' => 'CCELL TH205 EVO MAX — 0.5ml Glass Cartridge | Clear Plastic Mouthpiece',
        'slug' => 'ccell-th205-evo-max-05ml-clear-plastic',
        'sku' => 'TH205-EVOMAX-CPL',
        'hero_key' => 'glass-05ml-hero',
        'gallery_keys' => ['glass-main', 'glass-size', 'atomizer'],
        'content' => glass_content('0.5', 'Clear plastic mouthpiece', 'transparent'),
        'excerpt' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with clear plastic mouthpiece. Handles distillate through live rosin.',
        'yoast' => 'CCELL TH205 EVO MAX 0.5ml glass cartridge with clear plastic mouthpiece. Wholesale from Hamilton Devices.',
    ],

    // ── M6T10 EVO MAX (1.0ml Poly) ──
    [
        'title' => 'CCELL M6T10 EVO MAX — 1.0ml Poly Cartridge | Clear Plastic Mouthpiece',
        'slug' => 'ccell-m6t10-evo-max-1ml-clear-plastic',
        'sku' => 'M6T10-EVOMAX-CPL',
        'hero_key' => 'etp-10ml-hero',
        'gallery_keys' => ['etp-main', 'etp-size', 'atomizer'],
        'content' => etp_content('1.0', 'Clear plastic mouthpiece', 'transparent'),
        'excerpt' => 'CCELL M6T10 EVO MAX 1.0ml ETP poly cartridge with clear plastic mouthpiece. EVO MAX heating for all oil types.',
        'yoast' => 'CCELL M6T10 EVO MAX 1.0ml ETP cartridge with clear plastic mouthpiece. Wholesale from Hamilton Devices.',
    ],
    [
        'title' => 'CCELL M6T10 EVO MAX — 1.0ml Poly Cartridge | White Plastic Mouthpiece',
        'slug' => 'ccell-m6t10-evo-max-1ml-white-plastic',
        'sku' => 'M6T10-EVOMAX-WPL',
        'hero_key' => 'etp-10ml-hero',
        'gallery_keys' => ['etp-main', 'etp-size', 'atomizer'],
        'content' => etp_content('1.0', 'White plastic mouthpiece', 'lightweight'),
        'excerpt' => 'CCELL M6T10 EVO MAX 1.0ml ETP poly cartridge with white plastic mouthpiece. EVO MAX heating for all oil types.',
        'yoast' => 'CCELL M6T10 EVO MAX 1.0ml ETP cartridge with white plastic mouthpiece. Wholesale from Hamilton Devices.',
    ],
    [
        'title' => 'CCELL M6T10 EVO MAX — 1.0ml Poly Cartridge | Black Plastic Mouthpiece',
        'slug' => 'ccell-m6t10-evo-max-1ml-black-plastic',
        'sku' => 'M6T10-EVOMAX-BPL',
        'hero_key' => 'etp-10ml-hero',
        'gallery_keys' => ['etp-main', 'etp-size', 'atomizer'],
        'content' => etp_content('1.0', 'Black plastic mouthpiece', 'lightweight'),
        'excerpt' => 'CCELL M6T10 EVO MAX 1.0ml ETP poly cartridge with black plastic mouthpiece. EVO MAX heating for all oil types.',
        'yoast' => 'CCELL M6T10 EVO MAX 1.0ml ETP cartridge with black plastic mouthpiece. Wholesale from Hamilton Devices.',
    ],

    // ── M6T05 EVO MAX (0.5ml Poly) ──
    [
        'title' => 'CCELL M6T05 EVO MAX — 0.5ml Poly Cartridge | Clear Plastic Mouthpiece',
        'slug' => 'ccell-m6t05-evo-max-05ml-clear-plastic',
        'sku' => 'M6T05-EVOMAX-CPL',
        'hero_key' => 'etp-05ml-hero',
        'gallery_keys' => ['etp-main', 'etp-size', 'atomizer'],
        'content' => etp_content('0.5', 'Clear plastic mouthpiece', 'transparent'),
        'excerpt' => 'CCELL M6T05 EVO MAX 0.5ml ETP poly cartridge with clear plastic mouthpiece. EVO MAX heating for all oil types.',
        'yoast' => 'CCELL M6T05 EVO MAX 0.5ml ETP cartridge with clear plastic mouthpiece. Wholesale from Hamilton Devices.',
    ],
    [
        'title' => 'CCELL M6T05 EVO MAX — 0.5ml Poly Cartridge | White Plastic Mouthpiece',
        'slug' => 'ccell-m6t05-evo-max-05ml-white-plastic',
        'sku' => 'M6T05-EVOMAX-WPL',
        'hero_key' => 'etp-05ml-hero',
        'gallery_keys' => ['etp-main', 'etp-size', 'atomizer'],
        'content' => etp_content('0.5', 'White plastic mouthpiece', 'lightweight'),
        'excerpt' => 'CCELL M6T05 EVO MAX 0.5ml ETP poly cartridge with white plastic mouthpiece. EVO MAX heating for all oil types.',
        'yoast' => 'CCELL M6T05 EVO MAX 0.5ml ETP cartridge with white plastic mouthpiece. Wholesale from Hamilton Devices.',
    ],
    [
        'title' => 'CCELL M6T05 EVO MAX — 0.5ml Poly Cartridge | Black Plastic Mouthpiece',
        'slug' => 'ccell-m6t05-evo-max-05ml-black-plastic',
        'sku' => 'M6T05-EVOMAX-BPL',
        'hero_key' => 'etp-05ml-hero',
        'gallery_keys' => ['etp-main', 'etp-size', 'atomizer'],
        'content' => etp_content('0.5', 'Black plastic mouthpiece', 'lightweight'),
        'excerpt' => 'CCELL M6T05 EVO MAX 0.5ml ETP poly cartridge with black plastic mouthpiece. EVO MAX heating for all oil types.',
        'yoast' => 'CCELL M6T05 EVO MAX 0.5ml ETP cartridge with black plastic mouthpiece. Wholesale from Hamilton Devices.',
    ],
];

// ═══════════════════════════════════════════════════════════
// Create products
// ═══════════════════════════════════════════════════════════

echo "\n── Creating products ──\n";

$categories = [$CARTRIDGES_TT, $CCELL_TT, $EVOMAX_TT, $SIMPLE_TT];

foreach ($products as $p) {
    echo "\n  {$p['title']}\n";

    $meta = array_merge($EVOMAX_PRICING, [
        '_sku' => $p['sku'],
        '_yoast_wpseo_metadesc' => $p['yoast'],
    ]);

    $pid = create_product($conn, [
        'title' => $p['title'],
        'slug' => $p['slug'],
        'content' => $p['content'],
        'excerpt' => $p['excerpt'],
        'sku' => $p['sku'],
        'meta' => $meta,
    ], $categories);

    if (!$pid) continue;

    // Set hero image
    $hero_id = $attach_cache[$p['hero_key']] ?? null;
    if ($hero_id) {
        $existing = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=$pid AND meta_key='_thumbnail_id' LIMIT 1");
        if ($existing->num_rows > 0) {
            $conn->query("UPDATE wp_postmeta SET meta_value='$hero_id' WHERE post_id=$pid AND meta_key='_thumbnail_id'");
        } else {
            $conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($pid, '_thumbnail_id', '$hero_id')");
        }
    }

    // Set gallery
    $gallery_ids = [];
    foreach ($p['gallery_keys'] as $gk) {
        if (isset($attach_cache[$gk])) $gallery_ids[] = $attach_cache[$gk];
    }
    if (!empty($gallery_ids)) {
        $gallery_str = implode(',', $gallery_ids);
        $existing = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=$pid AND meta_key='_product_image_gallery' LIMIT 1");
        if ($existing->num_rows > 0) {
            $conn->query("UPDATE wp_postmeta SET meta_value='$gallery_str' WHERE post_id=$pid AND meta_key='_product_image_gallery'");
        } else {
            $conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($pid, '_product_image_gallery', '$gallery_str')");
        }
    }

    echo "    → ID $pid (hero: " . ($hero_id ?: 'none') . ", gallery: " . count($gallery_ids) . " imgs)\n";
}

// ═══════════════════════════════════════════════════════════
// Update term counts
// ═══════════════════════════════════════════════════════════

echo "\n── Updating term counts ──\n";
foreach ([$EVOMAX_TT, $CARTRIDGES_TT, $CCELL_TT] as $tt) {
    $cnt = $conn->query("SELECT COUNT(*) as c FROM wp_term_relationships tr JOIN wp_posts p ON tr.object_id=p.ID WHERE tr.term_taxonomy_id=$tt AND p.post_status='publish' AND p.post_type='product'")->fetch_assoc()['c'];
    $conn->query("UPDATE wp_term_taxonomy SET count=$cnt WHERE term_taxonomy_id=$tt");
    $name = $conn->query("SELECT t.name FROM wp_terms t JOIN wp_term_taxonomy tt ON t.term_id=tt.term_id WHERE tt.term_taxonomy_id=$tt")->fetch_assoc()['name'];
    echo "  $name: $cnt\n";
}

// Final listing
echo "\n── EVO MAX category now ──\n";
$result = $conn->query("SELECT p.ID, p.post_title FROM wp_term_relationships tr JOIN wp_posts p ON tr.object_id=p.ID WHERE tr.term_taxonomy_id=$EVOMAX_TT AND p.post_status='publish' AND p.post_type='product' ORDER BY p.post_title");
while ($row = $result->fetch_assoc()) {
    echo "  {$row['ID']}: {$row['post_title']}\n";
}

echo "\n=== Done ===\n";
$conn->close();
