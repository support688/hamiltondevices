<?php
/**
 * Create CCELL TH210 EVO MAX — 1.0ml Glass Cartridge | White Ceramic Mouthpiece
 *
 * Run via: docker compose exec -T wordpress php < create-evomax-th210-wc.php
 *
 * ⚠️  PRICING IS PLACEHOLDER — update retail_tiers, wholesale_tiers, price,
 *    lowest, and wholesale_price before running in production.
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---------- Category IDs ----------
$CAT_CARTRIDGE = 543;    // Parent: CCELL Vape Cartridges
$CAT_CCELL     = 1234;   // Brand: ccell
$CAT_EVO_MAX   = 1373;   // Technology: CCELL EVO MAX (term_taxonomy_id directly — see note below)
$PRODUCT_TYPE  = 2;       // simple product

// ---------- Helper ----------
function get_tt_id($conn, $term_id, $taxonomy) {
    $stmt = $conn->prepare("SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE term_id=? AND taxonomy=?");
    $stmt->bind_param('is', $term_id, $taxonomy);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['term_taxonomy_id'] : null;
}

$tt_cartridge = get_tt_id($conn, $CAT_CARTRIDGE, 'product_cat');
$tt_ccell     = get_tt_id($conn, $CAT_CCELL, 'product_cat');
$tt_simple    = get_tt_id($conn, $PRODUCT_TYPE, 'product_type');

// EVO MAX: look up tt_id from term_taxonomy table directly
// The category was created by setup-cartridge-categories.php with slug 'ccell-evo-max'
$r = $conn->query("
    SELECT tt.term_taxonomy_id
    FROM wp_term_taxonomy tt
    JOIN wp_terms t ON t.term_id = tt.term_id
    WHERE t.slug = 'ccell-evo-max' AND tt.taxonomy = 'product_cat'
    LIMIT 1
");
$row = $r->fetch_assoc();
$tt_evo_max = $row ? $row['term_taxonomy_id'] : null;

if (!$tt_evo_max) {
    die("ERROR: Could not find 'ccell-evo-max' category. Run setup-cartridge-categories.php first.\n");
}

echo "Term taxonomy IDs: cartridge=$tt_cartridge, ccell=$tt_ccell, evo_max=$tt_evo_max, simple=$tt_simple\n";

// ---------- Resolve gallery images ----------
// Look up EVOMAX attachment IDs from the uploads already in the media library
$gallery_ids = [];
$img_result = $conn->query("
    SELECT ID FROM wp_posts
    WHERE post_type = 'attachment'
      AND post_mime_type LIKE 'image/%'
      AND guid LIKE '%EVOMAX%'
    ORDER BY ID ASC
    LIMIT 5
");
while ($irow = $img_result->fetch_assoc()) {
    $gallery_ids[] = $irow['ID'];
}
$gallery_str = implode(',', $gallery_ids);
echo "Gallery attachment IDs: $gallery_str\n";

// Thumbnail: TH210EVO-WC.png (attachment 221038 per known data)
// Verify it exists; fall back to first gallery image
$thumb_id = 221038;
$thumb_check = $conn->query("SELECT ID FROM wp_posts WHERE ID = $thumb_id AND post_type = 'attachment'");
if ($thumb_check->num_rows === 0) {
    // Try to find by filename
    $thumb_r = $conn->query("
        SELECT ID FROM wp_posts
        WHERE post_type = 'attachment' AND guid LIKE '%TH210EVO-WC%'
        LIMIT 1
    ");
    $thumb_row = $thumb_r->fetch_assoc();
    $thumb_id = $thumb_row ? $thumb_row['ID'] : ($gallery_ids[0] ?? 0);
}
echo "Thumbnail attachment ID: $thumb_id\n";

// ---------- Product definition ----------
// ⚠️  PLACEHOLDER PRICING — replace before running
$product = [
    'title'       => 'CCELL TH210 EVO MAX — 1.0ml Glass Cartridge | White Ceramic Mouthpiece',
    'slug'        => 'ccell-th210-evo-max-1-0ml-glass-white-ceramic',
    'sku'         => 'TH210-EVOMAX-WC',
    'price'       => '4.99',       // ⚠️  PLACEHOLDER — update before running
    'lowest'      => '2.49',       // ⚠️  PLACEHOLDER
    'thumbnail'   => $thumb_id,
    'gallery'     => $gallery_str,

    'description' => 'The CCELL TH210 EVO MAX is a 1.0ml glass-body cartridge featuring the advanced EVO MAX oversized ceramic heating element. Engineered to handle the full viscosity spectrum — from thin distillates to thick live rosins and liquid diamonds — the EVO MAX coil delivers denser vapor, better flavor extraction, and consistent performance from the very first puff.

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

    'short_desc'  => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with oversized ceramic heating element and white ceramic mouthpiece. Handles every oil type — distillate, live resin, live rosin, and liquid diamonds — with denser vapor and better flavor from the first puff.',

    'meta_desc'   => 'CCELL TH210 EVO MAX 1.0ml glass cartridge with white ceramic mouthpiece. Oversized EVO MAX ceramic coil handles distillate through live rosin. Wholesale pricing from Hamilton Devices.',

    // ⚠️  ALL PRICING IS PLACEHOLDER — replace with actual pricing before running
    'retail_tiers' => [
        ['range' => '1-19',       'price' => '$4.99'],
        ['range' => '20-49',      'price' => '$4.19'],
        ['range' => '50-99',      'price' => '$3.49'],
        ['range' => '100-1,999',  'price' => '$2.99'],
        ['range' => '2,000+',     'price' => '$2.49'],
    ],
    'wholesale_price' => '2.99',   // ⚠️  PLACEHOLDER
    'wholesale_tiers' => [
        ['start' => '100', 'end' => '1999', 'price' => '2.99'],
        ['start' => '2000', 'end' => '',     'price' => '2.49'],
    ],
];

// ---------- 1. Create the post ----------
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
$stmt->bind_param('ssssssss',
    $now, $now_gmt, $product['description'], $product['title'], $product['short_desc'],
    $product['slug'],
    $now, $now_gmt
);
$stmt->execute();
$post_id = $conn->insert_id;

// Update GUID
$guid = "http://localhost:8080/?post_type=product&#038;p=$post_id";
$conn->query("UPDATE wp_posts SET guid='$guid' WHERE ID=$post_id");

echo "\nCreated product: {$product['title']} (ID: $post_id)\n";

// ---------- 2. Add taxonomy relationships ----------
$categories = [$tt_cartridge, $tt_ccell, $tt_evo_max, $tt_simple];
foreach ($categories as $tt_id) {
    $conn->query("INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($post_id, $tt_id, 0)");
}

// ---------- 3. Add core WooCommerce meta ----------
$meta = [
    '_sku'                => $product['sku'],
    '_regular_price'      => $product['price'],
    '_price'              => $product['price'],
    '_stock_status'       => 'instock',
    '_manage_stock'       => 'no',
    '_stock'              => null,
    '_backorders'         => 'no',
    '_sold_individually'  => 'no',
    '_virtual'            => 'no',
    '_downloadable'       => 'no',
    '_download_limit'     => '-1',
    '_download_expiry'    => '-1',
    '_tax_status'         => 'taxable',
    '_tax_class'          => '',
    '_product_version'    => '10.4.3',
    'total_sales'         => '0',
    '_thumbnail_id'       => (string)$product['thumbnail'],
    '_product_image_gallery' => $product['gallery'],
    '_visibility'         => 'visible',
    '_disabled_for_coupons' => 'no',

    // Bulk pricing (retail)
    '_bulkdiscount_enabled' => 'yes',
    'table_name'            => 'Bulk Pricing',
    '1st_column_name'       => 'Quantity',
    '2nd_column_name'       => 'Price per unit',
    'price_text'            => 'As low as',
    'lowest_price'          => $product['lowest'],

    // Wholesale base
    'wholesale_customer_have_wholesale_price'             => 'yes',
    'wholesale_customer_wholesale_price'                  => $product['wholesale_price'],
    'wholesale_customer_wholesale_minimum_order_quantity'  => '100',
    'wholesale_customer_wholesale_order_quantity_step'     => '100',
    'wtable_name'           => 'Wholesale Pricing',
    '1st_wcolumn_name'      => 'MOQ',
    '2nd_wcolumn_name'      => 'Unit Price',
    'wprice_text'           => 'As low as',
    'wlowest_price'         => $product['lowest'],

    // Wholesale plugin
    'wwpp_post_meta_enable_quantity_discount_rule' => 'yes',
    'wwpp_product_wholesale_visibility_filter'     => 'all',
    'wwpp_ignore_cat_level_wholesale_discount'     => 'no',
    'wwpp_ignore_role_level_wholesale_discount'    => 'no',

    // Yoast SEO
    '_yoast_wpseo_metadesc'          => $product['meta_desc'],
    '_yoast_wpseo_content_score'     => '90',
    '_yoast_wpseo_primary_product_cat' => (string)$CAT_CCELL,
];

$meta_stmt = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)");
foreach ($meta as $key => $value) {
    $val = $value ?? '';
    $meta_stmt->bind_param('iss', $post_id, $key, $val);
    $meta_stmt->execute();
}

// ---------- 4. Add retail pricing tiers ----------
foreach ($product['retail_tiers'] as $i => $tier) {
    $num = $i + 1;
    $key_qty = "quantity_limit_$num";
    $key_price = "price_per_unit_$num";
    $range_val = $tier['range'];
    $price_val = $tier['price'];
    $meta_stmt->bind_param('iss', $post_id, $key_qty, $range_val);
    $meta_stmt->execute();
    $meta_stmt->bind_param('iss', $post_id, $key_price, $price_val);
    $meta_stmt->execute();
}

// ---------- 5. Add wholesale pricing tiers ----------
foreach ($product['wholesale_tiers'] as $i => $tier) {
    $num = $i + 1;
    $key_qty = "wquantity_limit_$num";
    $key_price = "wprice_per_unit_$num";
    $wprice_display = '$' . $tier['price'];
    $wqty_val = "{$tier['start']}–{$tier['end']}";
    if (empty($tier['end'])) $wqty_val = "{$tier['start']}+";
    $meta_stmt->bind_param('iss', $post_id, $key_qty, $wqty_val);
    $meta_stmt->execute();
    $meta_stmt->bind_param('iss', $post_id, $key_price, $wprice_display);
    $meta_stmt->execute();
}
// Fix wholesale quantity display (comma formatting)
$wq1 = "100–1,999";
$wq2 = "2,000+";
$conn->query("UPDATE wp_postmeta SET meta_value='$wq1' WHERE post_id=$post_id AND meta_key='wquantity_limit_1'");
$conn->query("UPDATE wp_postmeta SET meta_value='$wq2' WHERE post_id=$post_id AND meta_key='wquantity_limit_2'");

// ---------- 6. Add wholesale discount rule mapping (serialized) ----------
$ww_mapping = serialize([
    [
        'wholesale_role' => 'wholesale_customer',
        'start_qty'      => $product['wholesale_tiers'][0]['start'],
        'end_qty'        => $product['wholesale_tiers'][0]['end'],
        'price_type'     => 'fixed-price',
        'wholesale_price' => $product['wholesale_tiers'][0]['price'],
    ],
    [
        'wholesale_role' => 'wholesale_customer',
        'start_qty'      => $product['wholesale_tiers'][1]['start'],
        'end_qty'        => $product['wholesale_tiers'][1]['end'],
        'price_type'     => 'fixed-price',
        'wholesale_price' => $product['wholesale_tiers'][1]['price'],
    ],
]);
$key_map = 'wwpp_post_meta_quantity_discount_rule_mapping';
$meta_stmt->bind_param('iss', $post_id, $key_map, $ww_mapping);
$meta_stmt->execute();

// ---------- 7. Flatsome product options ----------
$flatsome_opts = serialize([[
    '_product_block' => '0', '_top_content' => '', '_bottom_content' => '',
    '_bubble_new' => '', '_bubble_text' => '', '_custom_tab_title' => '',
    '_custom_tab' => '', '_product_video' => '', '_product_video_size' => '',
    '_product_video_placement' => '',
]]);
$key_wc = 'wc_productdata_options';
$meta_stmt->bind_param('iss', $post_id, $key_wc, $flatsome_opts);
$meta_stmt->execute();

echo "  -> Added meta, categories, and pricing tiers\n";

// ---------- 8. Update term counts ----------
foreach ([$tt_cartridge, $tt_ccell, $tt_evo_max] as $tt) {
    $conn->query("UPDATE wp_term_taxonomy SET count = (SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = $tt) WHERE term_taxonomy_id = $tt");
}
$new_count = $conn->query("SELECT count FROM wp_term_taxonomy WHERE term_taxonomy_id = $tt_evo_max")->fetch_assoc()['count'];
echo "Updated EVO MAX category count: $new_count\n";

echo "\nCreated product ID: $post_id\n";
echo "Slug: {$product['slug']}\n";
echo "URL: http://localhost:8080/product/{$product['slug']}/\n";
echo "\n⚠️  REMINDER: All pricing is PLACEHOLDER. Update retail_tiers, wholesale_tiers,\n";
echo "   price, lowest, and wholesale_price before running on production.\n";
echo "\nDone!\n";

$conn->close();
