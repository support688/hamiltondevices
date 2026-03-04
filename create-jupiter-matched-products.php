<?php
/**
 * Create new WooCommerce products from Jupiter catalog
 *
 * Products that Hamilton doesn't currently carry but Jupiter sells.
 * Follows the same pattern as create-evomax-th210-wc.php
 *
 * Run via: docker compose exec -T wordpress php < create-jupiter-matched-products.php
 *
 * WARNING: ALL PRICING IS PLACEHOLDER — update before production use.
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Jupiter-Matched Product Creation Script ===\n\n";

// =============================================================================
// Helpers
// =============================================================================

function get_tt_id_by_slug($conn, $slug, $taxonomy = 'product_cat') {
    $stmt = $conn->prepare("
        SELECT tt.term_taxonomy_id
        FROM wp_term_taxonomy tt
        JOIN wp_terms t ON t.term_id = tt.term_id
        WHERE t.slug = ? AND tt.taxonomy = ?
        LIMIT 1
    ");
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
    // Check if slug already exists
    if (slug_exists($conn, $p['slug'])) {
        echo "  SKIP: Slug '{$p['slug']}' already exists\n";
        return null;
    }

    $now = date('Y-m-d H:i:s');
    $now_gmt = gmdate('Y-m-d H:i:s');

    // 1. Create the post
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

    // 2. Add taxonomy relationships
    $all_cats = array_merge($categories, [$tt_simple]);
    foreach ($all_cats as $tt_id) {
        if ($tt_id) {
            $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($post_id, $tt_id, 0)");
        }
    }

    // 3. Add core WooCommerce meta
    $meta = [
        '_sku'                => $p['sku'],
        '_regular_price'      => $p['price'],
        '_price'              => $p['price'],
        '_stock_status'       => 'instock',
        '_manage_stock'       => 'no',
        '_stock'              => '',
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
        '_yoast_wpseo_primary_product_cat' => '1234',
    ];

    $meta_stmt = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)");
    foreach ($meta as $key => $value) {
        $val = $value ?? '';
        $meta_stmt->bind_param('iss', $post_id, $key, $val);
        $meta_stmt->execute();
    }

    // 4. Add retail pricing tiers
    foreach ($p['retail_tiers'] as $i => $tier) {
        $num = $i + 1;
        $key_qty = "quantity_limit_$num";
        $key_price = "price_per_unit_$num";
        $meta_stmt->bind_param('iss', $post_id, $key_qty, $tier['range']);
        $meta_stmt->execute();
        $meta_stmt->bind_param('iss', $post_id, $key_price, $tier['price']);
        $meta_stmt->execute();
    }

    // 5. Add wholesale pricing tiers
    foreach ($p['wholesale_tiers'] as $i => $tier) {
        $num = $i + 1;
        $wprice_display = '$' . $tier['price'];
        $wqty_val = "{$tier['start']}–{$tier['end']}";
        if (empty($tier['end'])) $wqty_val = "{$tier['start']}+";
        $key_qty = "wquantity_limit_$num";
        $key_price = "wprice_per_unit_$num";
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

    // 6. Add wholesale discount rule mapping
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

    // 7. Flatsome product options
    $flatsome_opts = serialize([[
        '_product_block' => '0', '_top_content' => '', '_bottom_content' => '',
        '_bubble_new' => '', '_bubble_text' => '', '_custom_tab_title' => '',
        '_custom_tab' => '', '_product_video' => '', '_product_video_size' => '',
        '_product_video_placement' => '',
    ]]);
    $key_wc = 'wc_productdata_options';
    $meta_stmt->bind_param('iss', $post_id, $key_wc, $flatsome_opts);
    $meta_stmt->execute();

    return $post_id;
}

// =============================================================================
// Resolve category term_taxonomy_ids
// =============================================================================

$CAT_CARTRIDGE  = 543;
$CAT_DISPOSABLE = 550;
$CAT_BATTERY    = 542;
$CAT_CCELL      = 1234;
$PRODUCT_TYPE   = 2;

$tt_cartridge    = get_tt_id_by_term_id($conn, $CAT_CARTRIDGE);
$tt_disposable   = get_tt_id_by_term_id($conn, $CAT_DISPOSABLE);
$tt_battery      = get_tt_id_by_term_id($conn, $CAT_BATTERY);
$tt_ccell        = get_tt_id_by_term_id($conn, $CAT_CCELL);
$tt_simple       = get_tt_id_by_term_id($conn, $PRODUCT_TYPE, 'product_type');

$tt_evo_max      = get_tt_id_by_slug($conn, 'ccell-evo-max');
$tt_easy         = get_tt_id_by_slug($conn, 'ccell-easy');
$tt_ceramic      = get_tt_id_by_slug($conn, 'ccell-ceramic-evo-max');
$tt_postless     = get_tt_id_by_slug($conn, 'ccell-3-postless');
$tt_aio_evomax   = get_tt_id_by_slug($conn, 'aio-evo-max');
$tt_aio_se       = get_tt_id_by_slug($conn, 'aio-se-standard');
$tt_aio_bio      = get_tt_id_by_slug($conn, 'aio-3-bio-heating');

// Pod category — create if it doesn't exist (term_id 1050 per plan)
$tt_pod = get_tt_id_by_term_id($conn, 1050);
if (!$tt_pod) {
    $tt_pod = get_tt_id_by_slug($conn, 'pod-systems');
}

echo "Category TT IDs:\n";
echo "  cartridge=$tt_cartridge, disposable=$tt_disposable, battery=$tt_battery, ccell=$tt_ccell\n";
echo "  evo_max=$tt_evo_max, easy=$tt_easy, ceramic=$tt_ceramic, postless=$tt_postless\n";
echo "  aio_evomax=$tt_aio_evomax, aio_se=$tt_aio_se, aio_bio=$tt_aio_bio, pod=$tt_pod\n\n";

// =============================================================================
// Standard pricing templates (PLACEHOLDER)
// =============================================================================

$CART_PRICING_PREMIUM = [
    'price' => '4.99', 'lowest' => '2.49', 'wholesale_price' => '2.99',
    'retail_tiers' => [
        ['range' => '1-19',       'price' => '$4.99'],
        ['range' => '20-49',      'price' => '$4.19'],
        ['range' => '50-99',      'price' => '$3.49'],
        ['range' => '100-1,999',  'price' => '$2.99'],
        ['range' => '2,000+',     'price' => '$2.49'],
    ],
    'wholesale_tiers' => [
        ['start' => '100', 'end' => '1999', 'price' => '2.99'],
        ['start' => '2000', 'end' => '',     'price' => '2.49'],
    ],
];

$CART_PRICING_HALF = [
    'price' => '4.49', 'lowest' => '2.19', 'wholesale_price' => '2.69',
    'retail_tiers' => [
        ['range' => '1-19',       'price' => '$4.49'],
        ['range' => '20-49',      'price' => '$3.69'],
        ['range' => '50-99',      'price' => '$3.09'],
        ['range' => '100-1,999',  'price' => '$2.69'],
        ['range' => '2,000+',     'price' => '$2.19'],
    ],
    'wholesale_tiers' => [
        ['start' => '100', 'end' => '1999', 'price' => '2.69'],
        ['start' => '2000', 'end' => '',     'price' => '2.19'],
    ],
];

$CERAMIC_PRICING = [
    'price' => '5.49', 'lowest' => '2.79', 'wholesale_price' => '3.29',
    'retail_tiers' => [
        ['range' => '1-19',       'price' => '$5.49'],
        ['range' => '20-49',      'price' => '$4.59'],
        ['range' => '50-99',      'price' => '$3.79'],
        ['range' => '100-1,999',  'price' => '$3.29'],
        ['range' => '2,000+',     'price' => '$2.79'],
    ],
    'wholesale_tiers' => [
        ['start' => '100', 'end' => '1999', 'price' => '3.29'],
        ['start' => '2000', 'end' => '',     'price' => '2.79'],
    ],
];

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

$BATTERY_PRICING = [
    'price' => '14.99', 'lowest' => '8.99', 'wholesale_price' => '9.99',
    'retail_tiers' => [
        ['range' => '1-19',       'price' => '$14.99'],
        ['range' => '20-49',      'price' => '$12.49'],
        ['range' => '50-99',      'price' => '$10.99'],
        ['range' => '100-1,999',  'price' => '$9.99'],
        ['range' => '2,000+',     'price' => '$8.99'],
    ],
    'wholesale_tiers' => [
        ['start' => '100', 'end' => '1999', 'price' => '9.99'],
        ['start' => '2000', 'end' => '',     'price' => '8.99'],
    ],
];

$POD_PRICING = [
    'price' => '12.99', 'lowest' => '7.49', 'wholesale_price' => '8.99',
    'retail_tiers' => [
        ['range' => '1-19',       'price' => '$12.99'],
        ['range' => '20-49',      'price' => '$10.99'],
        ['range' => '50-99',      'price' => '$9.49'],
        ['range' => '100-1,999',  'price' => '$8.99'],
        ['range' => '2,000+',     'price' => '$7.49'],
    ],
    'wholesale_tiers' => [
        ['start' => '100', 'end' => '1999', 'price' => '8.99'],
        ['start' => '2000', 'end' => '',     'price' => '7.49'],
    ],
];

// =============================================================================
// Product definitions
// =============================================================================

$products = [

    // ────────────────────────────────────────────────────────────────────────
    // CARTRIDGES
    // ────────────────────────────────────────────────────────────────────────

    // THREDZ 0.5ml (Ceramic EVO MAX)
    array_merge($CERAMIC_PRICING, [
        'title'       => 'CCELL THREDZ — 0.5ml All-Ceramic Cartridge | Threaded Body',
        'slug'        => 'ccell-thredz-0-5ml-ceramic-cartridge',
        'sku'         => 'THREDZ-05-CER',
        'categories'  => [$tt_cartridge, $tt_ccell, $tt_ceramic],
        'description' => 'The CCELL THREDZ is a 0.5ml all-ceramic cartridge with a threaded ceramic body and EVO MAX heating technology. The threaded ceramic construction provides a premium tactile feel while eliminating all metal contact with oil for the purest flavor delivery.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity</li>
<li>All-ceramic threaded body — zero metal oil contact</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Premium tactile threaded design</li>
</ul>',
        'short_desc'  => 'CCELL THREDZ 0.5ml all-ceramic cartridge with threaded body and EVO MAX coil. Zero metal contact for pure flavor.',
        'meta_desc'   => 'CCELL THREDZ 0.5ml all-ceramic threaded cartridge with EVO MAX heating. Zero metal oil contact. Wholesale from Hamilton Devices.',
    ]),

    // THREDZ 1.0ml (Ceramic EVO MAX)
    array_merge($CERAMIC_PRICING, [
        'title'       => 'CCELL THREDZ — 1.0ml All-Ceramic Cartridge | Threaded Body',
        'slug'        => 'ccell-thredz-1-0ml-ceramic-cartridge',
        'sku'         => 'THREDZ-10-CER',
        'categories'  => [$tt_cartridge, $tt_ccell, $tt_ceramic],
        'description' => 'The CCELL THREDZ is a 1.0ml all-ceramic cartridge with a threaded ceramic body and EVO MAX heating technology. Full-gram capacity with the purest flavor profile — no metal touches your oil.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity</li>
<li>All-ceramic threaded body — zero metal oil contact</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Premium tactile threaded design</li>
</ul>',
        'short_desc'  => 'CCELL THREDZ 1.0ml all-ceramic cartridge with threaded body and EVO MAX coil. Full-gram, zero metal contact.',
        'meta_desc'   => 'CCELL THREDZ 1.0ml all-ceramic threaded cartridge with EVO MAX heating. Wholesale from Hamilton Devices.',
    ]),

    // Blade 0.5ml (3.0 Postless)
    array_merge($CART_PRICING_HALF, [
        'title'       => 'CCELL Blade — 0.5ml Postless Ceramic Cartridge',
        'slug'        => 'ccell-blade-0-5ml-postless-cartridge',
        'sku'         => 'BLADE-05-PL',
        'categories'  => [$tt_cartridge, $tt_ccell, $tt_postless],
        'description' => 'The CCELL Blade is a 0.5ml postless cartridge featuring CCELL 3.0 Bio-Heating technology. The postless design eliminates the center post for maximum fill volume and the easiest filling process available.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity</li>
<li>Postless design — no center post</li>
<li>CCELL 3.0 Bio-ceramic heating element</li>
<li>Borosilicate glass body</li>
<li>510 thread connection</li>
<li>Easiest filling process of any CCELL cartridge</li>
</ul>',
        'short_desc'  => 'CCELL Blade 0.5ml postless cartridge with 3.0 Bio-Heating. No center post for easiest filling.',
        'meta_desc'   => 'CCELL Blade 0.5ml postless cartridge with 3.0 Bio-Heating technology. Wholesale from Hamilton Devices.',
    ]),

    // Blade 1.0ml (3.0 Postless)
    array_merge($CART_PRICING_PREMIUM, [
        'title'       => 'CCELL Blade — 1.0ml Postless Ceramic Cartridge',
        'slug'        => 'ccell-blade-1-0ml-postless-cartridge',
        'sku'         => 'BLADE-10-PL',
        'categories'  => [$tt_cartridge, $tt_ccell, $tt_postless],
        'description' => 'The CCELL Blade is a 1.0ml postless cartridge featuring CCELL 3.0 Bio-Heating technology. Full-gram capacity with postless design for maximum fill volume and easiest filling.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity</li>
<li>Postless design — no center post</li>
<li>CCELL 3.0 Bio-ceramic heating element</li>
<li>Borosilicate glass body</li>
<li>510 thread connection</li>
<li>Handles distillate, live resin, and live rosin</li>
</ul>',
        'short_desc'  => 'CCELL Blade 1.0ml postless cartridge with 3.0 Bio-Heating. Full-gram, no center post.',
        'meta_desc'   => 'CCELL Blade 1.0ml postless cartridge with 3.0 Bio-Heating. Wholesale from Hamilton Devices.',
    ]),

    // TurBoom 0.5ml (EVO MAX)
    array_merge($CART_PRICING_HALF, [
        'title'       => 'CCELL TurBoom — 0.5ml High-Power Cartridge | EVO MAX',
        'slug'        => 'ccell-turboom-0-5ml-cartridge',
        'sku'         => 'TURBOOM-05-EM',
        'categories'  => [$tt_cartridge, $tt_ccell, $tt_evo_max],
        'description' => 'The CCELL TurBoom is a 0.5ml high-power cartridge featuring the EVO MAX oversized ceramic heating element. Designed for maximum vapor production and bold flavor delivery, the TurBoom pushes the EVO MAX platform to its highest performance level.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity</li>
<li>High-power EVO MAX oversized ceramic element</li>
<li>Maximum vapor production</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Bold flavor profile</li>
</ul>',
        'short_desc'  => 'CCELL TurBoom 0.5ml high-power cartridge with EVO MAX coil. Maximum vapor and bold flavor.',
        'meta_desc'   => 'CCELL TurBoom 0.5ml high-power cartridge with EVO MAX ceramic. Maximum vapor production. Wholesale from Hamilton Devices.',
    ]),

    // TurBoom 1.0ml (EVO MAX)
    array_merge($CART_PRICING_PREMIUM, [
        'title'       => 'CCELL TurBoom — 1.0ml High-Power Cartridge | EVO MAX',
        'slug'        => 'ccell-turboom-1-0ml-cartridge',
        'sku'         => 'TURBOOM-10-EM',
        'categories'  => [$tt_cartridge, $tt_ccell, $tt_evo_max],
        'description' => 'The CCELL TurBoom is a 1.0ml high-power cartridge featuring the EVO MAX oversized ceramic heating element. Full-gram capacity with maximum vapor production and bold flavor delivery.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity</li>
<li>High-power EVO MAX oversized ceramic element</li>
<li>Maximum vapor production</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
</ul>',
        'short_desc'  => 'CCELL TurBoom 1.0ml high-power cartridge with EVO MAX coil. Full-gram, maximum vapor.',
        'meta_desc'   => 'CCELL TurBoom 1.0ml high-power cartridge with EVO MAX ceramic. Wholesale from Hamilton Devices.',
    ]),

    // Flexcell X 0.5ml (EVO MAX)
    array_merge($CART_PRICING_HALF, [
        'title'       => 'CCELL Flexcell X — 0.5ml Flexible Cartridge | EVO MAX',
        'slug'        => 'ccell-flexcell-x-0-5ml-cartridge',
        'sku'         => 'FLEXCELLX-05-EM',
        'categories'  => [$tt_cartridge, $tt_ccell, $tt_evo_max],
        'description' => 'The CCELL Flexcell X is a 0.5ml flexible-design cartridge featuring the EVO MAX oversized ceramic heating element. The innovative flexible architecture provides enhanced durability and unique branding opportunities.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Flexible design architecture</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Enhanced durability</li>
</ul>',
        'short_desc'  => 'CCELL Flexcell X 0.5ml flexible cartridge with EVO MAX coil. Innovative design with premium coil performance.',
        'meta_desc'   => 'CCELL Flexcell X 0.5ml flexible cartridge with EVO MAX ceramic. Wholesale from Hamilton Devices.',
    ]),

    // Flexcell X 1.0ml (EVO MAX)
    array_merge($CART_PRICING_PREMIUM, [
        'title'       => 'CCELL Flexcell X — 1.0ml Flexible Cartridge | EVO MAX',
        'slug'        => 'ccell-flexcell-x-1-0ml-cartridge',
        'sku'         => 'FLEXCELLX-10-EM',
        'categories'  => [$tt_cartridge, $tt_ccell, $tt_evo_max],
        'description' => 'The CCELL Flexcell X is a 1.0ml flexible-design cartridge featuring the EVO MAX oversized ceramic heating element. Full-gram capacity with innovative flexible architecture for durability and branding.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Flexible design architecture</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
</ul>',
        'short_desc'  => 'CCELL Flexcell X 1.0ml flexible cartridge with EVO MAX coil. Full-gram, innovative flexible design.',
        'meta_desc'   => 'CCELL Flexcell X 1.0ml flexible cartridge with EVO MAX ceramic. Wholesale from Hamilton Devices.',
    ]),

    // Infinity 0.5ml (EVO MAX)
    array_merge($CART_PRICING_HALF, [
        'title'       => 'CCELL Infinity — 0.5ml Bottom-Airflow Cartridge | EVO MAX',
        'slug'        => 'ccell-infinity-0-5ml-cartridge',
        'sku'         => 'INFINITY-05-EM',
        'categories'  => [$tt_cartridge, $tt_ccell, $tt_evo_max],
        'description' => 'The CCELL Infinity is a 0.5ml bottom-airflow cartridge featuring the EVO MAX oversized ceramic heating element. The bottom-airflow design delivers smoother draws and enhanced flavor consistency.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity</li>
<li>Bottom-airflow design for smoother draws</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Enhanced flavor consistency</li>
</ul>',
        'short_desc'  => 'CCELL Infinity 0.5ml bottom-airflow cartridge with EVO MAX coil. Smoother draws, better flavor.',
        'meta_desc'   => 'CCELL Infinity 0.5ml bottom-airflow cartridge with EVO MAX ceramic. Wholesale from Hamilton Devices.',
    ]),

    // Infinity 1.0ml (EVO MAX)
    array_merge($CART_PRICING_PREMIUM, [
        'title'       => 'CCELL Infinity — 1.0ml Bottom-Airflow Cartridge | EVO MAX',
        'slug'        => 'ccell-infinity-1-0ml-cartridge',
        'sku'         => 'INFINITY-10-EM',
        'categories'  => [$tt_cartridge, $tt_ccell, $tt_evo_max],
        'description' => 'The CCELL Infinity is a 1.0ml bottom-airflow cartridge featuring the EVO MAX oversized ceramic heating element. Full-gram capacity with bottom-airflow for smoother draws and enhanced flavor.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity</li>
<li>Bottom-airflow design for smoother draws</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
</ul>',
        'short_desc'  => 'CCELL Infinity 1.0ml bottom-airflow cartridge with EVO MAX coil. Full-gram, smoother draws.',
        'meta_desc'   => 'CCELL Infinity 1.0ml bottom-airflow cartridge with EVO MAX ceramic. Wholesale from Hamilton Devices.',
    ]),

    // Liquid X Glass 0.5ml (EVO MAX)
    array_merge($CART_PRICING_HALF, [
        'title'       => 'CCELL Liquid X — 0.5ml Glass Cartridge | EVO MAX',
        'slug'        => 'ccell-liquid-x-0-5ml-glass-cartridge',
        'sku'         => 'LIQUIDX-05-EM',
        'categories'  => [$tt_cartridge, $tt_ccell, $tt_evo_max],
        'description' => 'The CCELL Liquid X is a 0.5ml glass cartridge featuring the EVO MAX oversized ceramic heating element. Clean lines and premium glass construction with the most advanced CCELL coil platform.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity borosilicate glass tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Clean, modern design</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Full oil visibility</li>
</ul>',
        'short_desc'  => 'CCELL Liquid X 0.5ml glass cartridge with EVO MAX coil. Clean design, premium glass, all oils.',
        'meta_desc'   => 'CCELL Liquid X 0.5ml glass cartridge with EVO MAX ceramic. Wholesale from Hamilton Devices.',
    ]),

    // Liquid X Glass 1.0ml (EVO MAX)
    array_merge($CART_PRICING_PREMIUM, [
        'title'       => 'CCELL Liquid X — 1.0ml Glass Cartridge | EVO MAX',
        'slug'        => 'ccell-liquid-x-1-0ml-glass-cartridge',
        'sku'         => 'LIQUIDX-10-EM',
        'categories'  => [$tt_cartridge, $tt_ccell, $tt_evo_max],
        'description' => 'The CCELL Liquid X is a 1.0ml glass cartridge featuring the EVO MAX oversized ceramic heating element. Full-gram capacity with clean design, premium glass, and the most advanced CCELL coil.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity borosilicate glass tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>Clean, modern design</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
</ul>',
        'short_desc'  => 'CCELL Liquid X 1.0ml glass cartridge with EVO MAX coil. Full-gram, clean design, all oils.',
        'meta_desc'   => 'CCELL Liquid X 1.0ml glass cartridge with EVO MAX ceramic. Wholesale from Hamilton Devices.',
    ]),

    // ────────────────────────────────────────────────────────────────────────
    // AIO DISPOSABLES
    // ────────────────────────────────────────────────────────────────────────

    // Airone (EVO MAX AIO)
    array_merge($AIO_PRICING, [
        'title'       => 'CCELL Airone — All-In-One Disposable | EVO MAX',
        'slug'        => 'ccell-airone-aio-disposable',
        'sku'         => 'AIRONE-AIO-EM',
        'categories'  => [$tt_disposable, $tt_ccell, $tt_aio_evomax],
        'description' => 'The CCELL Airone is a premium all-in-one disposable featuring the EVO MAX oversized ceramic heating element with a built-in rechargeable battery. Sleek, lightweight form factor designed for brands seeking top-tier vapor quality in a ready-to-fill disposable format.

<strong>Key Features:</strong>
<ul>
<li>EVO MAX oversized ceramic heating element</li>
<li>Built-in rechargeable lithium-ion battery</li>
<li>USB-C charging</li>
<li>Draw-activated firing</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Sleek, lightweight design</li>
</ul>',
        'short_desc'  => 'CCELL Airone AIO disposable with EVO MAX coil and rechargeable battery. Premium vapor in a ready-to-fill format.',
        'meta_desc'   => 'CCELL Airone all-in-one disposable with EVO MAX ceramic and rechargeable battery. Wholesale from Hamilton Devices.',
    ]),

    // Easy Pod (EVO / Easy AIO)
    array_merge($AIO_PRICING, [
        'title'       => 'CCELL Easy Pod — All-In-One Disposable | EVO Standard',
        'slug'        => 'ccell-easy-pod-aio-disposable',
        'sku'         => 'EASYPOD-AIO-SE',
        'categories'  => [$tt_disposable, $tt_ccell, $tt_aio_se],
        'description' => 'The CCELL Easy Pod is a value-oriented all-in-one disposable featuring the proven EVO ceramic heating element. Optimized for distillate programs, the Easy Pod delivers reliable performance at the most competitive AIO price point.

<strong>Key Features:</strong>
<ul>
<li>EVO ceramic heating element</li>
<li>Built-in battery</li>
<li>Draw-activated firing</li>
<li>Optimized for distillate formulations</li>
<li>Most cost-effective AIO option</li>
<li>Ready to fill and ship</li>
</ul>',
        'short_desc'  => 'CCELL Easy Pod AIO disposable with EVO ceramic coil. Best value AIO for distillate programs.',
        'meta_desc'   => 'CCELL Easy Pod all-in-one disposable with EVO ceramic. Best value AIO. Wholesale from Hamilton Devices.',
    ]),

    // Luster Pro (EVO MAX AIO)
    array_merge($AIO_PRICING, [
        'title'       => 'CCELL Luster Pro — All-In-One Disposable | EVO MAX',
        'slug'        => 'ccell-luster-pro-aio-disposable',
        'sku'         => 'LUSTERPRO-AIO-EM',
        'categories'  => [$tt_disposable, $tt_ccell, $tt_aio_evomax],
        'description' => 'The CCELL Luster Pro is a premium all-in-one disposable featuring the EVO MAX oversized ceramic heating element. A refined form factor with advanced vapor performance for brands that demand the best in disposable hardware.

<strong>Key Features:</strong>
<ul>
<li>EVO MAX oversized ceramic heating element</li>
<li>Built-in rechargeable lithium-ion battery</li>
<li>USB-C charging</li>
<li>Draw-activated firing</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Premium refined form factor</li>
</ul>',
        'short_desc'  => 'CCELL Luster Pro AIO disposable with EVO MAX coil. Premium form factor, all oil types.',
        'meta_desc'   => 'CCELL Luster Pro all-in-one disposable with EVO MAX ceramic. Wholesale from Hamilton Devices.',
    ]),

    // ────────────────────────────────────────────────────────────────────────
    // POD SYSTEMS
    // ────────────────────────────────────────────────────────────────────────

    // Liquid Que
    array_merge($POD_PRICING, [
        'title'       => 'CCELL Liquid Que — Pod System',
        'slug'        => 'ccell-liquid-que-pod-system',
        'sku'         => 'LIQUIDQUE-POD',
        'categories'  => array_filter([$tt_pod, $tt_ccell]),
        'description' => 'The CCELL Liquid Que is a premium pod system combining a rechargeable battery device with replaceable CCELL cartridge pods. Designed for brands building a refillable or replaceable pod ecosystem with CCELL heating technology.

<strong>Key Features:</strong>
<ul>
<li>Rechargeable battery device</li>
<li>Replaceable CCELL pod cartridges</li>
<li>Draw-activated or button-activated (varies by model)</li>
<li>USB-C charging</li>
<li>Compact, pocket-friendly design</li>
<li>Compatible with CCELL pod ecosystem</li>
</ul>',
        'short_desc'  => 'CCELL Liquid Que pod system with rechargeable battery and replaceable CCELL pods.',
        'meta_desc'   => 'CCELL Liquid Que pod system. Rechargeable battery with replaceable pods. Wholesale from Hamilton Devices.',
    ]),

    // Kap
    array_merge($POD_PRICING, [
        'title'       => 'CCELL Kap — Pod System',
        'slug'        => 'ccell-kap-pod-system',
        'sku'         => 'KAP-POD',
        'categories'  => array_filter([$tt_pod, $tt_ccell]),
        'description' => 'The CCELL Kap is a compact pod system with a magnetic pod connection and rechargeable battery. The Kap is designed for discreet, on-the-go use with the reliability of CCELL ceramic heating technology.

<strong>Key Features:</strong>
<ul>
<li>Magnetic pod connection</li>
<li>Rechargeable battery</li>
<li>Draw-activated firing</li>
<li>USB-C charging</li>
<li>Ultra-compact, discreet design</li>
<li>CCELL ceramic heating technology</li>
</ul>',
        'short_desc'  => 'CCELL Kap compact pod system with magnetic connection and rechargeable battery.',
        'meta_desc'   => 'CCELL Kap pod system. Magnetic connection, rechargeable, ultra-compact. Wholesale from Hamilton Devices.',
    ]),

    // ────────────────────────────────────────────────────────────────────────
    // POWER SUPPLIES / BATTERIES
    // ────────────────────────────────────────────────────────────────────────

    // Palm SE
    array_merge($BATTERY_PRICING, [
        'title'       => 'CCELL Palm SE — 510 Thread Battery',
        'slug'        => 'ccell-palm-se-510-battery',
        'sku'         => 'PALMSE-510',
        'categories'  => [$tt_battery, $tt_ccell],
        'description' => 'The CCELL Palm SE is a compact 510-thread battery designed for use with any standard 510 cartridge. The palm-sized form factor features inhale-activated firing and a built-in rechargeable battery for convenient, buttonless operation.

<strong>Key Features:</strong>
<ul>
<li>510 thread connection — compatible with all standard cartridges</li>
<li>Draw-activated firing (no button)</li>
<li>Built-in rechargeable lithium-ion battery</li>
<li>USB-C charging</li>
<li>Compact palm-sized form factor</li>
<li>LED battery indicator</li>
</ul>',
        'short_desc'  => 'CCELL Palm SE 510-thread battery. Compact, draw-activated, USB-C rechargeable.',
        'meta_desc'   => 'CCELL Palm SE 510-thread battery. Compact, draw-activated, USB-C charging. Wholesale from Hamilton Devices.',
    ]),

    // M4 Tiny
    array_merge($BATTERY_PRICING, [
        'title'       => 'CCELL M4 Tiny — 510 Thread Battery',
        'slug'        => 'ccell-m4-tiny-510-battery',
        'sku'         => 'M4TINY-510',
        'categories'  => [$tt_battery, $tt_ccell],
        'description' => 'The CCELL M4 Tiny is an ultra-compact 510-thread battery — one of the smallest CCELL power supplies available. Perfect for discreet use with any standard 510 cartridge.

<strong>Key Features:</strong>
<ul>
<li>510 thread connection</li>
<li>Ultra-compact, ultra-lightweight design</li>
<li>Draw-activated or button-activated (varies by model)</li>
<li>Built-in rechargeable battery</li>
<li>USB-C charging</li>
<li>LED battery indicator</li>
</ul>',
        'short_desc'  => 'CCELL M4 Tiny ultra-compact 510-thread battery. One of the smallest CCELL power supplies.',
        'meta_desc'   => 'CCELL M4 Tiny ultra-compact 510 battery. Rechargeable, LED indicator. Wholesale from Hamilton Devices.',
    ]),

    // M4B Pro
    array_merge($BATTERY_PRICING, [
        'title'       => 'CCELL M4B Pro — 510 Thread Battery | Variable Voltage',
        'slug'        => 'ccell-m4b-pro-510-battery',
        'sku'         => 'M4BPRO-510',
        'categories'  => [$tt_battery, $tt_ccell],
        'description' => 'The CCELL M4B Pro is a professional-grade 510-thread battery with variable voltage settings. Multiple voltage modes let users dial in the perfect temperature for any oil type and viscosity.

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
        'short_desc'  => 'CCELL M4B Pro 510-thread battery with variable voltage. Professional-grade power supply for any cartridge.',
        'meta_desc'   => 'CCELL M4B Pro 510 battery with variable voltage. Preheat, USB-C, professional grade. Wholesale from Hamilton Devices.',
    ]),

    // Klik
    array_merge($BATTERY_PRICING, [
        'title'       => 'CCELL Klik — 510 Thread Battery | Magnetic Adapter',
        'slug'        => 'ccell-klik-510-battery',
        'sku'         => 'KLIK-510',
        'categories'  => [$tt_battery, $tt_ccell],
        'description' => 'The CCELL Klik is a 510-thread battery featuring a magnetic adapter system for quick, secure cartridge swapping. The magnetic connection provides a clean look and easy cartridge changes.

<strong>Key Features:</strong>
<ul>
<li>510 thread connection with magnetic adapter</li>
<li>Quick-swap cartridge design</li>
<li>Draw-activated firing</li>
<li>Built-in rechargeable battery</li>
<li>USB-C charging</li>
<li>LED battery indicator</li>
<li>Clean, flush cartridge fit</li>
</ul>',
        'short_desc'  => 'CCELL Klik 510-thread battery with magnetic adapter. Quick-swap cartridges, clean flush fit.',
        'meta_desc'   => 'CCELL Klik 510 battery with magnetic adapter for quick cartridge swapping. Wholesale from Hamilton Devices.',
    ]),
];

// =============================================================================
// Create products
// =============================================================================

$created_ids = [];
$skip_count = 0;

foreach ($products as $p) {
    $categories = $p['categories'] ?? [];
    unset($p['categories']);

    echo "Creating: {$p['title']}...\n";
    $post_id = create_product($conn, $p, $categories, $tt_simple);

    if ($post_id) {
        echo "  -> Created (ID: $post_id)\n";
        $created_ids[] = $post_id;
    } else {
        $skip_count++;
    }
}

// =============================================================================
// Update term counts for all affected categories
// =============================================================================

echo "\nUpdating term counts...\n";
$all_tt_ids = array_filter([
    $tt_cartridge, $tt_disposable, $tt_battery, $tt_ccell,
    $tt_evo_max, $tt_easy, $tt_ceramic, $tt_postless,
    $tt_aio_evomax, $tt_aio_se, $tt_aio_bio, $tt_pod,
]);

foreach ($all_tt_ids as $tt_id) {
    $conn->query("UPDATE wp_term_taxonomy SET count = (SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = $tt_id) WHERE term_taxonomy_id = $tt_id");
    $count = $conn->query("SELECT count FROM wp_term_taxonomy WHERE term_taxonomy_id = $tt_id")->fetch_assoc()['count'];
    echo "  TT $tt_id: $count products\n";
}

echo "\n=== Creation Complete ===\n";
echo "Created: " . count($created_ids) . " new products\n";
echo "Skipped: $skip_count (already exist)\n";
if ($created_ids) {
    echo "Product IDs: " . implode(', ', $created_ids) . "\n";
}
echo "\nWARNING: All pricing is PLACEHOLDER. Update before production use.\n";
echo "Done!\n";

$conn->close();
