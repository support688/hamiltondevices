<?php
/**
 * Create CCELL Easy (EVO) cartridge products
 * - TH205-Easy 0.5ml Glass
 * - TH210-Easy 1.0ml Glass
 * - M6T10-Easy 1.0ml Poly
 * Also assigns M6T05-SE (230111) to Easy category
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Category IDs
$CAT_CARTRIDGE = 543;    // Parent: CCELL Vape Cartridges
$CAT_CCELL     = 1234;   // Brand: ccell
$CAT_EASY      = 1372;   // Technology: CCELL Easy (EVO)
$PRODUCT_TYPE  = 2;       // simple product

// Get term_taxonomy_ids for the categories
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
$tt_easy      = get_tt_id($conn, $CAT_EASY, 'product_cat');
$tt_simple    = get_tt_id($conn, $PRODUCT_TYPE, 'product_type');

echo "Term taxonomy IDs: cartridge=$tt_cartridge, ccell=$tt_ccell, easy=$tt_easy, simple=$tt_simple\n";

// Product definitions
$products = [
    [
        'title'       => 'CCELL TH205-Easy - 0.5ML Glass Cartridge with Snap-Fit Mouthpiece',
        'slug'        => 'ccell-th205-easy-0-5ml-glass-cartridge-snap-fit',
        'sku'         => 'TH205-EASY-SF',
        'price'       => '4.09',
        'lowest'      => '1.95',
        'thumbnail'   => 238488, // CCELL-TH205-S-Cartridge
        'gallery'     => '229970,229978', // Flat-TH2-S-0.5ML, Flute-TH2-S-0.5ML
        'description' => 'The CCELL TH205-Easy is a 0.5ml glass-body cartridge featuring the proven EVO heating coil. The glass tank provides excellent oil visibility and a premium look, while the snap-fit mouthpiece design allows for quick, tool-free assembly. An excellent value option for brands seeking reliable CCELL performance at a competitive price point.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity glass tank</li>
<li>EVO ceramic heating coil</li>
<li>Snap-fit mouthpiece connection</li>
<li>510 thread connection</li>
<li>Available in ceramic or plastic mouthpiece</li>
<li>Leak-proof design</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 0.5ml</li>
<li>Body: Borosilicate glass</li>
<li>Coil: EVO ceramic</li>
<li>Resistance: ~1.4-1.5Ω</li>
<li>Thread: 510</li>
<li>Mouthpiece: Snap-fit</li>
<li>Intake Holes: 4 × 1.2mm</li>
</ul>',
        'short_desc'  => 'CCELL TH205-Easy 0.5ml glass cartridge with EVO heating coil and snap-fit mouthpiece. Best value glass cartridge for high-volume programs.',
        'meta_desc'   => 'CCELL TH205-Easy 0.5ml glass cartridge with EVO ceramic coil. Snap-fit mouthpiece, 510 thread, leak-proof design. Wholesale pricing available from Hamilton Devices.',
        'retail_tiers' => [
            ['range' => '1-19',       'price' => '$4.09'],
            ['range' => '20-49',      'price' => '$3.35'],
            ['range' => '50-99',      'price' => '$2.68'],
            ['range' => '100-1,999',  'price' => '$2.15'],
            ['range' => '2,000+',     'price' => '$1.95'],
        ],
        'wholesale_price' => '2.15',
        'wholesale_tiers' => [
            ['start' => '100', 'end' => '1999', 'price' => '2.15'],
            ['start' => '2000', 'end' => '',     'price' => '1.95'],
        ],
    ],
    [
        'title'       => 'CCELL TH210-Easy - 1.0ML Glass Cartridge with Snap-Fit Mouthpiece',
        'slug'        => 'ccell-th210-easy-1-0ml-glass-cartridge-snap-fit',
        'sku'         => 'TH210-EASY-SF',
        'price'       => '4.19',
        'lowest'      => '1.99',
        'thumbnail'   => 221592, // TH210-S-White Ceramic Screw (closest TH210-S image)
        'gallery'     => '230101,229980', // Flat-TH2-S-1ML, Flute-TH2-S-1ML
        'description' => 'The CCELL TH210-Easy is a 1.0ml glass-body cartridge featuring the proven EVO heating coil. The larger capacity glass tank is ideal for brands offering full-gram products, with excellent oil visibility and premium aesthetics. The snap-fit mouthpiece design allows for quick, tool-free assembly during filling operations.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity glass tank</li>
<li>EVO ceramic heating coil</li>
<li>Snap-fit mouthpiece connection</li>
<li>510 thread connection</li>
<li>Available in ceramic or plastic mouthpiece</li>
<li>Leak-proof design</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: Borosilicate glass</li>
<li>Coil: EVO ceramic</li>
<li>Resistance: ~1.4-1.5Ω</li>
<li>Thread: 510</li>
<li>Mouthpiece: Snap-fit</li>
<li>Intake Holes: 4 × 1.6mm</li>
</ul>',
        'short_desc'  => 'CCELL TH210-Easy 1.0ml glass cartridge with EVO heating coil and snap-fit mouthpiece. Best value full-gram glass cartridge.',
        'meta_desc'   => 'CCELL TH210-Easy 1.0ml glass cartridge with EVO ceramic coil. Snap-fit mouthpiece, 510 thread, leak-proof design. Wholesale pricing available from Hamilton Devices.',
        'retail_tiers' => [
            ['range' => '1-19',       'price' => '$4.19'],
            ['range' => '20-49',      'price' => '$3.43'],
            ['range' => '50-99',      'price' => '$2.75'],
            ['range' => '100-1,999',  'price' => '$2.20'],
            ['range' => '2,000+',     'price' => '$1.99'],
        ],
        'wholesale_price' => '2.20',
        'wholesale_tiers' => [
            ['start' => '100', 'end' => '1999', 'price' => '2.20'],
            ['start' => '2000', 'end' => '',     'price' => '1.99'],
        ],
    ],
    [
        'title'       => 'CCELL M6T10-Easy - 1.0ML Poly Cartridge with Snap-Fit Mouthpiece',
        'slug'        => 'ccell-m6t10-easy-1-0ml-poly-cartridge-snap-fit',
        'sku'         => 'M6T10-EASY-SF',
        'price'       => '3.99',
        'lowest'      => '1.89',
        'thumbnail'   => 221021, // M6T10-SE1 (closest M6T 1.0ml image)
        'gallery'     => '221022,221023', // M6T10-SE2, M6T10-SE3
        'description' => 'The CCELL M6T10-Easy is a 1.0ml polycarbonate-body cartridge featuring the proven EVO heating coil. The durable poly tank is ideal for brands prioritizing durability and cost-effectiveness for full-gram products. The snap-fit mouthpiece design allows for quick, tool-free assembly during filling operations.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity polycarbonate tank</li>
<li>EVO ceramic heating coil</li>
<li>Snap-fit mouthpiece connection</li>
<li>510 thread connection</li>
<li>Plastic mouthpiece</li>
<li>Leak-proof design</li>
<li>Shatter-resistant body</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: Polycarbonate</li>
<li>Coil: EVO ceramic</li>
<li>Resistance: ~1.4-1.5Ω</li>
<li>Thread: 510</li>
<li>Mouthpiece: Snap-fit (plastic)</li>
<li>Intake Holes: 4 × 1.6mm</li>
</ul>',
        'short_desc'  => 'CCELL M6T10-Easy 1.0ml polycarbonate cartridge with EVO heating coil and snap-fit mouthpiece. Best value full-gram poly cartridge.',
        'meta_desc'   => 'CCELL M6T10-Easy 1.0ml poly cartridge with EVO ceramic coil. Snap-fit mouthpiece, 510 thread, shatter-resistant. Wholesale pricing from Hamilton Devices.',
        'retail_tiers' => [
            ['range' => '1-19',       'price' => '$3.99'],
            ['range' => '20-49',      'price' => '$3.27'],
            ['range' => '50-99',      'price' => '$2.61'],
            ['range' => '100-1,999',  'price' => '$2.09'],
            ['range' => '2,000+',     'price' => '$1.89'],
        ],
        'wholesale_price' => '2.09',
        'wholesale_tiers' => [
            ['start' => '100', 'end' => '1999', 'price' => '2.09'],
            ['start' => '2000', 'end' => '',     'price' => '1.89'],
        ],
    ],
];

$created_ids = [];

foreach ($products as $p) {
    // 1. Create the post
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
        $now, $now_gmt, $p['description'], $p['title'], $p['short_desc'],
        $p['slug'],
        $now, $now_gmt
    );
    $stmt->execute();
    $post_id = $conn->insert_id;

    // Update GUID
    $guid = "http://localhost:8080/?post_type=product&#038;p=$post_id";
    $conn->query("UPDATE wp_posts SET guid='$guid' WHERE ID=$post_id");

    echo "Created product: {$p['title']} (ID: $post_id)\n";
    $created_ids[] = $post_id;

    // 2. Add taxonomy relationships
    $categories = [$tt_cartridge, $tt_ccell, $tt_easy, $tt_simple];
    foreach ($categories as $tt_id) {
        $conn->query("INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($post_id, $tt_id, 0)");
    }

    // 3. Add core WooCommerce meta
    $meta = [
        '_sku'                => $p['sku'],
        '_regular_price'      => $p['price'],
        '_price'              => $p['price'],
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
        '_thumbnail_id'       => (string)$p['thumbnail'],
        '_product_image_gallery' => $p['gallery'],
        '_visibility'         => 'visible',
        '_disabled_for_coupons' => 'no',

        // Bulk pricing (retail)
        '_bulkdiscount_enabled' => 'yes',
        'table_name'            => 'Bulk Pricing',
        '1st_column_name'       => 'Quantity',
        '2nd_column_name'       => 'Price per unit',
        'price_text'            => 'As low as',
        'lowest_price'          => $p['lowest'],

        // Wholesale base
        'wholesale_customer_have_wholesale_price'             => 'yes',
        'wholesale_customer_wholesale_price'                  => $p['wholesale_price'],
        'wholesale_customer_wholesale_minimum_order_quantity'  => '100',
        'wholesale_customer_wholesale_order_quantity_step'     => '100',
        'wtable_name'           => 'Wholesale Pricing',
        '1st_wcolumn_name'      => 'MOQ',
        '2nd_wcolumn_name'      => 'Unit Price',
        'wprice_text'           => 'As low as',
        'wlowest_price'         => $p['lowest'],

        // Wholesale plugin
        'wwpp_post_meta_enable_quantity_discount_rule' => 'yes',
        'wwpp_product_wholesale_visibility_filter'     => 'all',
        'wwpp_ignore_cat_level_wholesale_discount'     => 'no',
        'wwpp_ignore_role_level_wholesale_discount'    => 'no',

        // Yoast SEO
        '_yoast_wpseo_metadesc'          => $p['meta_desc'],
        '_yoast_wpseo_content_score'     => '90',
        '_yoast_wpseo_primary_product_cat' => (string)$CAT_CCELL,
    ];

    $meta_stmt = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)");
    foreach ($meta as $key => $value) {
        $val = $value ?? '';
        $meta_stmt->bind_param('iss', $post_id, $key, $val);
        $meta_stmt->execute();
    }

    // Add retail pricing tiers
    foreach ($p['retail_tiers'] as $i => $tier) {
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

    // Add wholesale pricing tiers
    foreach ($p['wholesale_tiers'] as $i => $tier) {
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
    // Fix wholesale quantity display
    $wq1 = "100–1,999";
    $wq2 = "2,000+";
    $conn->query("UPDATE wp_postmeta SET meta_value='$wq1' WHERE post_id=$post_id AND meta_key='wquantity_limit_1'");
    $conn->query("UPDATE wp_postmeta SET meta_value='$wq2' WHERE post_id=$post_id AND meta_key='wquantity_limit_2'");

    // Add wholesale discount rule mapping (serialized)
    $ww_mapping = serialize([
        [
            'wholesale_role' => 'wholesale_customer',
            'start_qty'      => $p['wholesale_tiers'][0]['start'],
            'end_qty'        => $p['wholesale_tiers'][0]['end'],
            'price_type'     => 'fixed-price',
            'wholesale_price' => $p['wholesale_tiers'][0]['price'],
        ],
        [
            'wholesale_role' => 'wholesale_customer',
            'start_qty'      => $p['wholesale_tiers'][1]['start'],
            'end_qty'        => $p['wholesale_tiers'][1]['end'],
            'price_type'     => 'fixed-price',
            'wholesale_price' => $p['wholesale_tiers'][1]['price'],
        ],
    ]);
    $key_map = 'wwpp_post_meta_quantity_discount_rule_mapping';
    $meta_stmt->bind_param('iss', $post_id, $key_map, $ww_mapping);
    $meta_stmt->execute();

    // Add wc_productdata_options (empty Flatsome options)
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
}

// 4. Also assign M6T05-SE (230111) to the Easy category if not already
$check = $conn->query("SELECT * FROM wp_term_relationships WHERE object_id=230111 AND term_taxonomy_id=$tt_easy");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES (230111, $tt_easy, 0)");
    echo "\nAssigned M6T05-SE (230111) to CCELL Easy category\n";
} else {
    echo "\nM6T05-SE (230111) already in CCELL Easy category\n";
}

// 5. Update term counts
$conn->query("UPDATE wp_term_taxonomy SET count = (SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = $tt_easy) WHERE term_taxonomy_id = $tt_easy");
$new_count = $conn->query("SELECT count FROM wp_term_taxonomy WHERE term_taxonomy_id = $tt_easy")->fetch_assoc()['count'];
echo "Updated CCELL Easy category count: $new_count\n";

// Also update cartridge and ccell counts
$conn->query("UPDATE wp_term_taxonomy SET count = (SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = $tt_cartridge) WHERE term_taxonomy_id = $tt_cartridge");
$conn->query("UPDATE wp_term_taxonomy SET count = (SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = $tt_ccell) WHERE term_taxonomy_id = $tt_ccell");

echo "\nCreated product IDs: " . implode(', ', $created_ids) . "\n";
echo "Done!\n";

$conn->close();
