<?php
/**
 * Upgrade existing Hamilton products that match Jupiter's catalog
 *
 * Adds rich descriptions, placeholder pricing tiers, correct technology
 * subcategory assignments, and Yoast SEO meta to existing bare-bones products.
 *
 * Run via: docker compose exec -T wordpress php < upgrade-existing-products.php
 *
 * WARNING: ALL PRICING IS PLACEHOLDER — update before production use.
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Hamilton Product Upgrade Script ===\n\n";

// =============================================================================
// Helper functions
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

function ensure_meta($conn, $post_id, $key, $value) {
    $stmt = $conn->prepare("SELECT meta_id FROM wp_postmeta WHERE post_id=? AND meta_key=? LIMIT 1");
    $stmt->bind_param('is', $post_id, $key);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        $stmt2 = $conn->prepare("UPDATE wp_postmeta SET meta_value=? WHERE meta_id=?");
        $stmt2->bind_param('si', $value, $existing['meta_id']);
        $stmt2->execute();
    } else {
        $stmt2 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)");
        $stmt2->bind_param('iss', $post_id, $key, $value);
        $stmt2->execute();
    }
}

function ensure_term_relationship($conn, $post_id, $tt_id) {
    $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($post_id, $tt_id, 0)");
}

function add_pricing_meta($conn, $post_id, $product) {
    // Core WooCommerce pricing
    ensure_meta($conn, $post_id, '_regular_price', $product['price']);
    ensure_meta($conn, $post_id, '_price', $product['price']);
    ensure_meta($conn, $post_id, '_stock_status', 'instock');
    ensure_meta($conn, $post_id, '_manage_stock', 'no');
    ensure_meta($conn, $post_id, '_backorders', 'no');
    ensure_meta($conn, $post_id, '_sold_individually', 'no');
    ensure_meta($conn, $post_id, '_virtual', 'no');
    ensure_meta($conn, $post_id, '_downloadable', 'no');
    ensure_meta($conn, $post_id, '_tax_status', 'taxable');
    ensure_meta($conn, $post_id, '_visibility', 'visible');

    // Bulk pricing (retail)
    ensure_meta($conn, $post_id, '_bulkdiscount_enabled', 'yes');
    ensure_meta($conn, $post_id, 'table_name', 'Bulk Pricing');
    ensure_meta($conn, $post_id, '1st_column_name', 'Quantity');
    ensure_meta($conn, $post_id, '2nd_column_name', 'Price per unit');
    ensure_meta($conn, $post_id, 'price_text', 'As low as');
    ensure_meta($conn, $post_id, 'lowest_price', $product['lowest']);

    // Retail tiers
    foreach ($product['retail_tiers'] as $i => $tier) {
        $num = $i + 1;
        ensure_meta($conn, $post_id, "quantity_limit_$num", $tier['range']);
        ensure_meta($conn, $post_id, "price_per_unit_$num", $tier['price']);
    }

    // Wholesale base
    ensure_meta($conn, $post_id, 'wholesale_customer_have_wholesale_price', 'yes');
    ensure_meta($conn, $post_id, 'wholesale_customer_wholesale_price', $product['wholesale_price']);
    ensure_meta($conn, $post_id, 'wholesale_customer_wholesale_minimum_order_quantity', '100');
    ensure_meta($conn, $post_id, 'wholesale_customer_wholesale_order_quantity_step', '100');
    ensure_meta($conn, $post_id, 'wtable_name', 'Wholesale Pricing');
    ensure_meta($conn, $post_id, '1st_wcolumn_name', 'MOQ');
    ensure_meta($conn, $post_id, '2nd_wcolumn_name', 'Unit Price');
    ensure_meta($conn, $post_id, 'wprice_text', 'As low as');
    ensure_meta($conn, $post_id, 'wlowest_price', $product['lowest']);

    // Wholesale tiers
    foreach ($product['wholesale_tiers'] as $i => $tier) {
        $num = $i + 1;
        $wprice_display = '$' . $tier['price'];
        $wqty_val = "{$tier['start']}–{$tier['end']}";
        if (empty($tier['end'])) $wqty_val = "{$tier['start']}+";
        ensure_meta($conn, $post_id, "wquantity_limit_$num", $wqty_val);
        ensure_meta($conn, $post_id, "wprice_per_unit_$num", $wprice_display);
    }
    // Fix wholesale quantity display (comma formatting)
    ensure_meta($conn, $post_id, 'wquantity_limit_1', '100–1,999');
    ensure_meta($conn, $post_id, 'wquantity_limit_2', '2,000+');

    // Wholesale plugin flags
    ensure_meta($conn, $post_id, 'wwpp_post_meta_enable_quantity_discount_rule', 'yes');
    ensure_meta($conn, $post_id, 'wwpp_product_wholesale_visibility_filter', 'all');
    ensure_meta($conn, $post_id, 'wwpp_ignore_cat_level_wholesale_discount', 'no');
    ensure_meta($conn, $post_id, 'wwpp_ignore_role_level_wholesale_discount', 'no');

    // Wholesale discount rule mapping (serialized)
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
    ensure_meta($conn, $post_id, 'wwpp_post_meta_quantity_discount_rule_mapping', $ww_mapping);
}

// =============================================================================
// Resolve category term_taxonomy_ids
// =============================================================================

$CAT_CARTRIDGE = 543;
$CAT_CCELL     = 1234;

$tt_cartridge = get_tt_id_by_term_id($conn, $CAT_CARTRIDGE);
$tt_ccell     = get_tt_id_by_term_id($conn, $CAT_CCELL);

$tt_evo_max       = get_tt_id_by_slug($conn, 'ccell-evo-max');
$tt_easy          = get_tt_id_by_slug($conn, 'ccell-easy');
$tt_ceramic       = get_tt_id_by_slug($conn, 'ccell-ceramic-evo-max');
$tt_postless      = get_tt_id_by_slug($conn, 'ccell-3-postless');

echo "Category TT IDs:\n";
echo "  cartridge=$tt_cartridge, ccell=$tt_ccell\n";
echo "  evo_max=$tt_evo_max, easy=$tt_easy, ceramic=$tt_ceramic, postless=$tt_postless\n\n";

if (!$tt_evo_max || !$tt_easy || !$tt_postless) {
    die("ERROR: Missing technology subcategories. Run setup-cartridge-categories.php first.\n");
}

// =============================================================================
// Products to upgrade
// =============================================================================

$upgrades = [

    // ── M6T05-EVO 0.5ml variants (EVO MAX tier) ──
    [
        'id'          => 221083,
        'tier_tt'     => $tt_evo_max,
        'price'       => '4.49',
        'lowest'      => '2.19',
        'wholesale_price' => '2.69',
        'description' => 'The CCELL M6T05-EVO is a 0.5ml polycarbonate cartridge featuring the EVO MAX oversized ceramic heating element. The shatter-resistant poly body provides durability for high-volume programs, while the EVO MAX coil delivers superior vapor quality across the full viscosity spectrum.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity polycarbonate tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Shatter-resistant body</li>
<li>No break-in period — activates on first puff</li>
</ul>',
        'short_desc'  => 'CCELL M6T05-EVO 0.5ml poly cartridge with EVO MAX ceramic coil. Handles every oil type with denser vapor and better flavor.',
        'meta_desc'   => 'CCELL M6T05-EVO 0.5ml polycarbonate cartridge with EVO MAX oversized ceramic. Distillate through live rosin. Wholesale pricing from Hamilton Devices.',
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
    ],
    [
        'id'          => 221082,
        'tier_tt'     => $tt_evo_max,
        'price'       => '4.49',
        'lowest'      => '2.19',
        'wholesale_price' => '2.69',
        'description' => 'The CCELL M6T05-EVO is a 0.5ml polycarbonate cartridge featuring the EVO MAX oversized ceramic heating element. The shatter-resistant poly body provides durability for high-volume programs, while the EVO MAX coil delivers superior vapor quality across the full viscosity spectrum.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity polycarbonate tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Shatter-resistant body</li>
</ul>',
        'short_desc'  => 'CCELL M6T05-EVO 0.5ml poly cartridge with EVO MAX ceramic coil. Handles every oil type with denser vapor and better flavor.',
        'meta_desc'   => 'CCELL M6T05-EVO 0.5ml polycarbonate cartridge with EVO MAX oversized ceramic. Wholesale pricing from Hamilton Devices.',
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
    ],
    [
        'id'          => 221078,
        'tier_tt'     => $tt_evo_max,
        'price'       => '4.49',
        'lowest'      => '2.19',
        'wholesale_price' => '2.69',
        'description' => 'The CCELL M6T05-EVO is a 0.5ml polycarbonate cartridge featuring the EVO MAX oversized ceramic heating element. Premium vapor quality across all oil types in a durable poly body.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity polycarbonate tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Shatter-resistant body</li>
</ul>',
        'short_desc'  => 'CCELL M6T05-EVO 0.5ml poly cartridge with EVO MAX ceramic coil. Premium vapor from every oil type.',
        'meta_desc'   => 'CCELL M6T05-EVO 0.5ml polycarbonate cartridge with EVO MAX ceramic. Wholesale pricing from Hamilton Devices.',
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
    ],
    [
        'id'          => 221074,
        'tier_tt'     => $tt_evo_max,
        'price'       => '4.49',
        'lowest'      => '2.19',
        'wholesale_price' => '2.69',
        'description' => 'The CCELL M6T05-EVO is a 0.5ml polycarbonate cartridge featuring the EVO MAX oversized ceramic heating element. Premium vapor quality across all oil types in a durable poly body.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity polycarbonate tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Shatter-resistant body</li>
</ul>',
        'short_desc'  => 'CCELL M6T05-EVO 0.5ml poly cartridge with EVO MAX ceramic coil. Premium vapor from every oil type.',
        'meta_desc'   => 'CCELL M6T05-EVO 0.5ml polycarbonate cartridge with EVO MAX ceramic. Wholesale pricing from Hamilton Devices.',
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
    ],

    // ── M6T10-EVO 1.0ml variants (EVO MAX tier) ──
    [
        'id'          => 221032,
        'tier_tt'     => $tt_evo_max,
        'price'       => '4.99',
        'lowest'      => '2.49',
        'wholesale_price' => '2.99',
        'description' => 'The CCELL M6T10-EVO is a 1.0ml polycarbonate cartridge featuring the EVO MAX oversized ceramic heating element. The full-gram capacity and shatter-resistant poly body make it ideal for high-volume programs requiring premium coil performance.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity polycarbonate tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
<li>Shatter-resistant body</li>
<li>No break-in period — activates on first puff</li>
</ul>',
        'short_desc'  => 'CCELL M6T10-EVO 1.0ml poly cartridge with EVO MAX ceramic coil. Full-gram capacity, handles every oil type.',
        'meta_desc'   => 'CCELL M6T10-EVO 1.0ml polycarbonate cartridge with EVO MAX oversized ceramic. Wholesale pricing from Hamilton Devices.',
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
    ],
    [
        'id'          => 220993,
        'tier_tt'     => $tt_evo_max,
        'price'       => '4.99',
        'lowest'      => '2.49',
        'wholesale_price' => '2.99',
        'description' => 'The CCELL M6T10-EVO is a 1.0ml polycarbonate cartridge featuring the EVO MAX oversized ceramic heating element. Full-gram capacity with premium coil performance across all oil types.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity polycarbonate tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Shatter-resistant body</li>
</ul>',
        'short_desc'  => 'CCELL M6T10-EVO 1.0ml poly cartridge with EVO MAX ceramic coil. Full-gram capacity for all oil types.',
        'meta_desc'   => 'CCELL M6T10-EVO 1.0ml polycarbonate cartridge with EVO MAX ceramic. Wholesale pricing from Hamilton Devices.',
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
    ],
    [
        'id'          => 220987,
        'tier_tt'     => $tt_evo_max,
        'price'       => '4.99',
        'lowest'      => '2.49',
        'wholesale_price' => '2.99',
        'description' => 'The CCELL M6T10-EVO is a 1.0ml polycarbonate cartridge featuring the EVO MAX oversized ceramic heating element. Full-gram capacity with premium coil performance across all oil types.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity polycarbonate tank</li>
<li>EVO MAX oversized ceramic heating element</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Shatter-resistant body</li>
</ul>',
        'short_desc'  => 'CCELL M6T10-EVO 1.0ml poly cartridge with EVO MAX ceramic coil. Full-gram for all oil types.',
        'meta_desc'   => 'CCELL M6T10-EVO 1.0ml polycarbonate cartridge with EVO MAX ceramic. Wholesale pricing from Hamilton Devices.',
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
    ],

    // ── M6T05-S 0.5ml variants (Easy tier) ──
    [
        'id'          => 221071,
        'tier_tt'     => $tt_easy,
        'price'       => '3.49',
        'lowest'      => '1.69',
        'wholesale_price' => '2.09',
        'description' => 'The CCELL M6T05-S is a 0.5ml polycarbonate cartridge featuring the proven EVO ceramic heating element. The most cost-effective CCELL option for high-volume distillate programs.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity polycarbonate tank</li>
<li>EVO ceramic heating coil</li>
<li>510 thread connection</li>
<li>Optimized for distillate and live resin</li>
<li>Shatter-resistant body</li>
<li>Best value CCELL cartridge</li>
</ul>',
        'short_desc'  => 'CCELL M6T05-S 0.5ml poly cartridge with EVO ceramic coil. Best value option for high-volume distillate programs.',
        'meta_desc'   => 'CCELL M6T05-S 0.5ml polycarbonate cartridge with EVO ceramic coil. Best value wholesale pricing from Hamilton Devices.',
        'retail_tiers' => [
            ['range' => '1-19',       'price' => '$3.49'],
            ['range' => '20-49',      'price' => '$2.89'],
            ['range' => '50-99',      'price' => '$2.39'],
            ['range' => '100-1,999',  'price' => '$2.09'],
            ['range' => '2,000+',     'price' => '$1.69'],
        ],
        'wholesale_tiers' => [
            ['start' => '100', 'end' => '1999', 'price' => '2.09'],
            ['start' => '2000', 'end' => '',     'price' => '1.69'],
        ],
    ],
    [
        'id'          => 221070,
        'tier_tt'     => $tt_easy,
        'price'       => '3.49',
        'lowest'      => '1.69',
        'wholesale_price' => '2.09',
        'description' => 'The CCELL M6T05-S is a 0.5ml polycarbonate cartridge featuring the proven EVO ceramic heating element. Best value CCELL option for high-volume programs.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity polycarbonate tank</li>
<li>EVO ceramic heating coil</li>
<li>510 thread connection</li>
<li>Optimized for distillate and live resin</li>
<li>Shatter-resistant body</li>
</ul>',
        'short_desc'  => 'CCELL M6T05-S 0.5ml poly cartridge with EVO ceramic coil. Best value for high-volume programs.',
        'meta_desc'   => 'CCELL M6T05-S 0.5ml polycarbonate cartridge with EVO ceramic. Wholesale pricing from Hamilton Devices.',
        'retail_tiers' => [
            ['range' => '1-19',       'price' => '$3.49'],
            ['range' => '20-49',      'price' => '$2.89'],
            ['range' => '50-99',      'price' => '$2.39'],
            ['range' => '100-1,999',  'price' => '$2.09'],
            ['range' => '2,000+',     'price' => '$1.69'],
        ],
        'wholesale_tiers' => [
            ['start' => '100', 'end' => '1999', 'price' => '2.09'],
            ['start' => '2000', 'end' => '',     'price' => '1.69'],
        ],
    ],
    [
        'id'          => 221066,
        'tier_tt'     => $tt_easy,
        'price'       => '3.49',
        'lowest'      => '1.69',
        'wholesale_price' => '2.09',
        'description' => 'The CCELL M6T05-S is a 0.5ml polycarbonate cartridge featuring the proven EVO ceramic heating element. Best value CCELL option for high-volume programs.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity polycarbonate tank</li>
<li>EVO ceramic heating coil</li>
<li>510 thread connection</li>
<li>Optimized for distillate and live resin</li>
<li>Shatter-resistant body</li>
</ul>',
        'short_desc'  => 'CCELL M6T05-S 0.5ml poly cartridge with EVO ceramic coil. Best value for high-volume programs.',
        'meta_desc'   => 'CCELL M6T05-S 0.5ml polycarbonate cartridge with EVO ceramic. Wholesale pricing from Hamilton Devices.',
        'retail_tiers' => [
            ['range' => '1-19',       'price' => '$3.49'],
            ['range' => '20-49',      'price' => '$2.89'],
            ['range' => '50-99',      'price' => '$2.39'],
            ['range' => '100-1,999',  'price' => '$2.09'],
            ['range' => '2,000+',     'price' => '$1.69'],
        ],
        'wholesale_tiers' => [
            ['start' => '100', 'end' => '1999', 'price' => '2.09'],
            ['start' => '2000', 'end' => '',     'price' => '1.69'],
        ],
    ],
    [
        'id'          => 221062,
        'tier_tt'     => $tt_easy,
        'price'       => '3.49',
        'lowest'      => '1.69',
        'wholesale_price' => '2.09',
        'description' => 'The CCELL M6T05-S is a 0.5ml polycarbonate cartridge featuring the proven EVO ceramic heating element. Best value CCELL option for high-volume programs.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity polycarbonate tank</li>
<li>EVO ceramic heating coil</li>
<li>510 thread connection</li>
<li>Optimized for distillate and live resin</li>
<li>Shatter-resistant body</li>
</ul>',
        'short_desc'  => 'CCELL M6T05-S 0.5ml poly cartridge with EVO ceramic coil. Best value for high-volume programs.',
        'meta_desc'   => 'CCELL M6T05-S 0.5ml polycarbonate cartridge with EVO ceramic. Wholesale pricing from Hamilton Devices.',
        'retail_tiers' => [
            ['range' => '1-19',       'price' => '$3.49'],
            ['range' => '20-49',      'price' => '$2.89'],
            ['range' => '50-99',      'price' => '$2.39'],
            ['range' => '100-1,999',  'price' => '$2.09'],
            ['range' => '2,000+',     'price' => '$1.69'],
        ],
        'wholesale_tiers' => [
            ['start' => '100', 'end' => '1999', 'price' => '2.09'],
            ['start' => '2000', 'end' => '',     'price' => '1.69'],
        ],
    ],

    // ── Klean products (3.0 Postless tier) ──
    [
        'id'          => 196993,
        'tier_tt'     => $tt_postless,
        'price'       => '4.79',
        'lowest'      => '2.39',
        'wholesale_price' => '2.89',
        'description' => 'The CCELL Klean is a postless 510-thread cartridge featuring CCELL 3.0 Bio-Heating technology. The revolutionary postless design eliminates the center post for the largest possible fill chamber and easiest filling process of any CCELL cartridge.

<strong>Key Features:</strong>
<ul>
<li>Postless design — no center post for maximum fill volume</li>
<li>CCELL 3.0 Bio-ceramic heating element</li>
<li>Borosilicate glass body</li>
<li>510 thread connection</li>
<li>Easiest filling process — no navigating around posts</li>
<li>Handles distillate, live resin, and live rosin</li>
</ul>',
        'short_desc'  => 'CCELL Klean postless cartridge with 3.0 Bio-Heating. Easiest fill, largest chamber, clean vapor delivery.',
        'meta_desc'   => 'CCELL Klean postless cartridge with 3.0 Bio-Heating technology. Easiest filling, largest chamber. Wholesale pricing from Hamilton Devices.',
        'retail_tiers' => [
            ['range' => '1-19',       'price' => '$4.79'],
            ['range' => '20-49',      'price' => '$3.99'],
            ['range' => '50-99',      'price' => '$3.29'],
            ['range' => '100-1,999',  'price' => '$2.89'],
            ['range' => '2,000+',     'price' => '$2.39'],
        ],
        'wholesale_tiers' => [
            ['start' => '100', 'end' => '1999', 'price' => '2.89'],
            ['start' => '2000', 'end' => '',     'price' => '2.39'],
        ],
    ],
    [
        'id'          => 196978,
        'tier_tt'     => $tt_postless,
        'price'       => '4.79',
        'lowest'      => '2.39',
        'wholesale_price' => '2.89',
        'description' => 'The CCELL Klean is a postless 510-thread cartridge featuring CCELL 3.0 Bio-Heating technology. Postless design for maximum fill volume and easiest filling.

<strong>Key Features:</strong>
<ul>
<li>Postless design — no center post</li>
<li>CCELL 3.0 Bio-ceramic heating element</li>
<li>Borosilicate glass body</li>
<li>510 thread connection</li>
<li>Handles distillate, live resin, and live rosin</li>
</ul>',
        'short_desc'  => 'CCELL Klean postless cartridge with 3.0 Bio-Heating. Easiest fill, largest chamber.',
        'meta_desc'   => 'CCELL Klean postless cartridge with 3.0 Bio-Heating. Wholesale pricing from Hamilton Devices.',
        'retail_tiers' => [
            ['range' => '1-19',       'price' => '$4.79'],
            ['range' => '20-49',      'price' => '$3.99'],
            ['range' => '50-99',      'price' => '$3.29'],
            ['range' => '100-1,999',  'price' => '$2.89'],
            ['range' => '2,000+',     'price' => '$2.39'],
        ],
        'wholesale_tiers' => [
            ['start' => '100', 'end' => '1999', 'price' => '2.89'],
            ['start' => '2000', 'end' => '',     'price' => '2.39'],
        ],
    ],
    [
        'id'          => 192435,
        'tier_tt'     => $tt_postless,
        'price'       => '4.79',
        'lowest'      => '2.39',
        'wholesale_price' => '2.89',
        'description' => 'The CCELL Klean is a postless 510-thread cartridge featuring CCELL 3.0 Bio-Heating technology. Postless design for maximum fill volume and easiest filling.

<strong>Key Features:</strong>
<ul>
<li>Postless design — no center post</li>
<li>CCELL 3.0 Bio-ceramic heating element</li>
<li>Borosilicate glass body</li>
<li>510 thread connection</li>
<li>Handles distillate, live resin, and live rosin</li>
</ul>',
        'short_desc'  => 'CCELL Klean postless cartridge with 3.0 Bio-Heating. Easiest fill, largest chamber.',
        'meta_desc'   => 'CCELL Klean postless cartridge with 3.0 Bio-Heating. Wholesale pricing from Hamilton Devices.',
        'retail_tiers' => [
            ['range' => '1-19',       'price' => '$4.79'],
            ['range' => '20-49',      'price' => '$3.99'],
            ['range' => '50-99',      'price' => '$3.29'],
            ['range' => '100-1,999',  'price' => '$2.89'],
            ['range' => '2,000+',     'price' => '$2.39'],
        ],
        'wholesale_tiers' => [
            ['start' => '100', 'end' => '1999', 'price' => '2.89'],
            ['start' => '2000', 'end' => '',     'price' => '2.39'],
        ],
    ],

    // ── M6T05-SE (Easy tier) ──
    [
        'id'          => 230111,
        'tier_tt'     => $tt_easy,
        'price'       => '3.79',
        'lowest'      => '1.79',
        'wholesale_price' => '2.19',
        'description' => 'The CCELL M6T05-SE (Special Edition) is a 0.5ml polycarbonate cartridge featuring the EVO ceramic heating element with enhanced design touches. Combines proven EVO coil performance with a refined aesthetic for brands that want reliable hardware with a premium look.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity polycarbonate tank</li>
<li>EVO ceramic heating coil</li>
<li>Special Edition design refinements</li>
<li>510 thread connection</li>
<li>Optimized for distillate and live resin</li>
<li>Shatter-resistant body</li>
</ul>',
        'short_desc'  => 'CCELL M6T05-SE Special Edition 0.5ml poly cartridge with EVO ceramic coil. Proven performance with refined aesthetics.',
        'meta_desc'   => 'CCELL M6T05-SE Special Edition 0.5ml poly cartridge with EVO ceramic coil. Wholesale pricing from Hamilton Devices.',
        'retail_tiers' => [
            ['range' => '1-19',       'price' => '$3.79'],
            ['range' => '20-49',      'price' => '$3.09'],
            ['range' => '50-99',      'price' => '$2.49'],
            ['range' => '100-1,999',  'price' => '$2.19'],
            ['range' => '2,000+',     'price' => '$1.79'],
        ],
        'wholesale_tiers' => [
            ['start' => '100', 'end' => '1999', 'price' => '2.19'],
            ['start' => '2000', 'end' => '',     'price' => '1.79'],
        ],
    ],

    // ── Kera (Ceramic EVO MAX tier) ──
    [
        'id'          => 171374,
        'tier_tt'     => $tt_ceramic,
        'price'       => '5.49',
        'lowest'      => '2.79',
        'wholesale_price' => '3.29',
        'description' => 'The CCELL Kera is an all-ceramic cartridge featuring EVO MAX heating technology. The entire oil path — body, mouthpiece, and heating element — is ceramic, eliminating all metal contact with oil for the purest possible flavor profile.

<strong>Key Features:</strong>
<ul>
<li>All-ceramic body — zero metal contact with oil</li>
<li>EVO MAX ceramic heating element</li>
<li>Threaded ceramic mouthpiece</li>
<li>510 thread connection</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Purest flavor profile of any CCELL cartridge</li>
</ul>',
        'short_desc'  => 'CCELL Kera all-ceramic cartridge with EVO MAX heating. Zero metal oil contact for the purest flavor.',
        'meta_desc'   => 'CCELL Kera all-ceramic cartridge with EVO MAX heating technology. Zero metal contact with oil. Wholesale pricing from Hamilton Devices.',
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
    ],
];

// =============================================================================
// Execute upgrades
// =============================================================================

$success_count = 0;
$error_count = 0;

foreach ($upgrades as $p) {
    $post_id = $p['id'];

    // Verify product exists
    $check = $conn->query("SELECT ID, post_title, post_status FROM wp_posts WHERE ID=$post_id AND post_type='product'");
    $row = $check->fetch_assoc();
    if (!$row) {
        echo "  SKIP: Product ID $post_id not found in database\n";
        $error_count++;
        continue;
    }

    echo "Upgrading: {$row['post_title']} (ID: $post_id)...\n";

    // 1. Update post_content and post_excerpt
    $now = date('Y-m-d H:i:s');
    $now_gmt = gmdate('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE wp_posts SET post_content=?, post_excerpt=?, post_modified=?, post_modified_gmt=?, post_status='publish' WHERE ID=?");
    $stmt->bind_param('ssssi', $p['description'], $p['short_desc'], $now, $now_gmt, $post_id);
    $stmt->execute();

    // 2. Ensure technology subcategory assignment
    ensure_term_relationship($conn, $post_id, $p['tier_tt']);
    // Also ensure parent categories
    ensure_term_relationship($conn, $post_id, $tt_cartridge);
    ensure_term_relationship($conn, $post_id, $tt_ccell);

    // 3. Add pricing meta
    add_pricing_meta($conn, $post_id, $p);

    // 4. Add Yoast SEO meta
    ensure_meta($conn, $post_id, '_yoast_wpseo_metadesc', $p['meta_desc']);
    ensure_meta($conn, $post_id, '_yoast_wpseo_content_score', '90');
    ensure_meta($conn, $post_id, '_yoast_wpseo_primary_product_cat', (string)$CAT_CCELL);

    echo "  -> Updated descriptions, pricing, categories, SEO\n";
    $success_count++;
}

// =============================================================================
// Update term counts
// =============================================================================

echo "\nUpdating term counts...\n";
$all_tt_ids = array_filter([$tt_cartridge, $tt_ccell, $tt_evo_max, $tt_easy, $tt_ceramic, $tt_postless]);
foreach ($all_tt_ids as $tt_id) {
    $conn->query("UPDATE wp_term_taxonomy SET count = (SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = $tt_id) WHERE term_taxonomy_id = $tt_id");
    $count = $conn->query("SELECT count FROM wp_term_taxonomy WHERE term_taxonomy_id = $tt_id")->fetch_assoc()['count'];
    echo "  TT $tt_id: $count products\n";
}

echo "\n=== Upgrade Complete ===\n";
echo "Success: $success_count products upgraded\n";
echo "Errors: $error_count products skipped\n";
echo "\nWARNING: All pricing is PLACEHOLDER. Update before production use.\n";

$conn->close();
