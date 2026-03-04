<?php
/**
 * Fix incorrectly created Jupiter-matched products
 *
 * Deletes products that were created with wrong product types, categories,
 * and specs, then recreates them correctly based on actual Jupiter Research
 * product pages.
 *
 * Run via: docker compose exec -T wordpress php < fix-jupiter-products.php
 *
 * WARNING: ALL PRICING IS PLACEHOLDER
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Fix Jupiter-Matched Products ===\n\n";

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

function delete_product($conn, $post_id) {
    // Delete post meta
    $conn->query("DELETE FROM wp_postmeta WHERE post_id = $post_id");
    // Delete term relationships
    $conn->query("DELETE FROM wp_term_relationships WHERE object_id = $post_id");
    // Delete the post
    $conn->query("DELETE FROM wp_posts WHERE ID = $post_id");
    echo "  Deleted product ID $post_id\n";
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
    $stmt->bind_param('ssssssss',
        $now, $now_gmt, $p['description'], $p['title'], $p['short_desc'],
        $p['slug'], $now, $now_gmt
    );
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

    // Core meta
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

// =============================================================================
// Category TT IDs
// =============================================================================

$tt_cartridge    = get_tt_id_by_term_id($conn, 543);
$tt_disposable   = get_tt_id_by_term_id($conn, 550);
$tt_battery      = get_tt_id_by_term_id($conn, 542);
$tt_ccell        = get_tt_id_by_term_id($conn, 1234);
$tt_simple       = get_tt_id_by_term_id($conn, 2, 'product_type');

$tt_evo_max      = get_tt_id_by_slug($conn, 'ccell-evo-max');
$tt_easy         = get_tt_id_by_slug($conn, 'ccell-easy');
$tt_ceramic      = get_tt_id_by_slug($conn, 'ccell-ceramic-evo-max');
$tt_postless     = get_tt_id_by_slug($conn, 'ccell-3-postless');
$tt_aio_evomax   = get_tt_id_by_slug($conn, 'aio-evo-max');
$tt_aio_se       = get_tt_id_by_slug($conn, 'aio-se-standard');
$tt_aio_bio      = get_tt_id_by_slug($conn, 'aio-3-bio-heating');

$tt_pod = get_tt_id_by_term_id($conn, 1050);

echo "Category TT IDs resolved.\n\n";

// =============================================================================
// Pricing templates (PLACEHOLDER)
// =============================================================================

$AIO_PRICING = [
    'price'=>'9.99','lowest'=>'5.99','wholesale_price'=>'6.99',
    'retail_tiers'=>[
        ['range'=>'1-19','price'=>'$9.99'],['range'=>'20-49','price'=>'$8.49'],
        ['range'=>'50-99','price'=>'$7.49'],['range'=>'100-1,999','price'=>'$6.99'],
        ['range'=>'2,000+','price'=>'$5.99'],
    ],
    'wholesale_tiers'=>[
        ['start'=>'100','end'=>'1999','price'=>'6.99'],
        ['start'=>'2000','end'=>'','price'=>'5.99'],
    ],
];

$BATTERY_PRICING = [
    'price'=>'14.99','lowest'=>'8.99','wholesale_price'=>'9.99',
    'retail_tiers'=>[
        ['range'=>'1-19','price'=>'$14.99'],['range'=>'20-49','price'=>'$12.49'],
        ['range'=>'50-99','price'=>'$10.99'],['range'=>'100-1,999','price'=>'$9.99'],
        ['range'=>'2,000+','price'=>'$8.99'],
    ],
    'wholesale_tiers'=>[
        ['start'=>'100','end'=>'1999','price'=>'9.99'],
        ['start'=>'2000','end'=>'','price'=>'8.99'],
    ],
];

$POD_PRICING = [
    'price'=>'12.99','lowest'=>'7.49','wholesale_price'=>'8.99',
    'retail_tiers'=>[
        ['range'=>'1-19','price'=>'$12.99'],['range'=>'20-49','price'=>'$10.99'],
        ['range'=>'50-99','price'=>'$9.49'],['range'=>'100-1,999','price'=>'$8.99'],
        ['range'=>'2,000+','price'=>'$7.49'],
    ],
    'wholesale_tiers'=>[
        ['start'=>'100','end'=>'1999','price'=>'8.99'],
        ['start'=>'2000','end'=>'','price'=>'7.49'],
    ],
];

$CART_PRICING_HALF = [
    'price'=>'4.49','lowest'=>'2.19','wholesale_price'=>'2.69',
    'retail_tiers'=>[
        ['range'=>'1-19','price'=>'$4.49'],['range'=>'20-49','price'=>'$3.69'],
        ['range'=>'50-99','price'=>'$3.09'],['range'=>'100-1,999','price'=>'$2.69'],
        ['range'=>'2,000+','price'=>'$2.19'],
    ],
    'wholesale_tiers'=>[
        ['start'=>'100','end'=>'1999','price'=>'2.69'],
        ['start'=>'2000','end'=>'','price'=>'2.19'],
    ],
];

$CART_PRICING_PREMIUM = [
    'price'=>'4.99','lowest'=>'2.49','wholesale_price'=>'2.99',
    'retail_tiers'=>[
        ['range'=>'1-19','price'=>'$4.99'],['range'=>'20-49','price'=>'$4.19'],
        ['range'=>'50-99','price'=>'$3.49'],['range'=>'100-1,999','price'=>'$2.99'],
        ['range'=>'2,000+','price'=>'$2.49'],
    ],
    'wholesale_tiers'=>[
        ['start'=>'100','end'=>'1999','price'=>'2.99'],
        ['start'=>'2000','end'=>'','price'=>'2.49'],
    ],
];

// =============================================================================
// STEP 1: Delete incorrectly created products (IDs from create-jupiter-matched script)
// =============================================================================

echo "── Step 1: Deleting incorrectly created products ──\n";

// Products created by create-jupiter-matched-products.php that need deletion
$delete_ids = [
    243020, // TurBoom 0.5ml (wrong: was cartridge, is actually AIO 2.0mL)
    243021, // TurBoom 1.0ml (wrong: was cartridge, is actually AIO 2.0mL)
    243018, // Blade 0.5ml (wrong: was cartridge, is actually AIO)
    243019, // Blade 1.0ml (wrong: was cartridge, is actually AIO)
    243022, // Flexcell X 0.5ml (wrong: was cartridge, is actually AIO)
    243023, // Flexcell X 1.0ml (wrong: was cartridge, is actually AIO)
    243024, // Infinity 0.5ml (wrong: was EVO MAX cartridge, is actually EVO AIO)
    243025, // Infinity 1.0ml (wrong: was EVO MAX cartridge, is actually EVO AIO)
    243026, // Liquid X 0.5ml (wrong: was cartridge, is actually AIO DS01)
    243027, // Liquid X 1.0ml (wrong: was cartridge, is actually AIO DS01)
    243028, // Airone (wrong: was AIO EVO MAX, actually uses 3.0 Bio-Heating)
    243029, // Easy Pod (wrong: was AIO SE, actually uses EVOMAX)
    243030, // Luster Pro (wrong: was AIO EVO MAX, actually uses SE and is a pod system)
    243031, // Liquid Que (correct product type but needs category fix)
    243032, // Kap (wrong: was pod system, is actually 510 power supply)
    243036, // Klik (wrong: was 510 battery, is actually a dispenser — skip for now)
];

foreach ($delete_ids as $did) {
    $check = $conn->query("SELECT ID, post_title FROM wp_posts WHERE ID=$did AND post_type='product'");
    $row = $check->fetch_assoc();
    if ($row) {
        echo "  Deleting: {$row['post_title']} (ID: $did)\n";
        delete_product($conn, $did);
    } else {
        echo "  SKIP: ID $did not found\n";
    }
}

// Also fix THREDZ — they exist but have wrong category (ccell-ceramic-evo-max → should be EVO platform cartridge)
echo "\n── Step 1b: Fix THREDZ category (EVO, not Ceramic EVOMAX) ──\n";
foreach ([243016, 243017] as $thredz_id) {
    $check = $conn->query("SELECT ID, post_title FROM wp_posts WHERE ID=$thredz_id AND post_type='product'");
    $row = $check->fetch_assoc();
    if ($row) {
        // Remove ceramic-evo-max category
        if ($tt_ceramic) {
            $conn->query("DELETE FROM wp_term_relationships WHERE object_id=$thredz_id AND term_taxonomy_id=$tt_ceramic");
        }
        // We don't have an "EVO" subcategory — THREDZ uses EVO platform but it's a cartridge
        // For now assign to ccell-evo-max since it's the closest (EVOMAX is the upgrade of EVO)
        // NOTE: Jupiter says THREDZ uses EVO platform, but Hamilton may want to categorize differently
        if ($tt_evo_max) {
            $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($thredz_id, $tt_evo_max, 0)");
        }
        echo "  Fixed THREDZ {$row['post_title']} (ID: $thredz_id): removed ceramic, added evo-max\n";
    }
}

// Update THREDZ descriptions to reference EVO platform correctly
$thredz_desc_05 = 'The CCELL THREDZ is the first patented stackable 510-thread cartridge. The 0.5ml borosilicate glass reservoir pairs with the CCELL EVO Atomizer Platform and a threaded metal mouthpiece connector that enables stacking multiple cartridges for strain blending.

The Linear Coupling Technology uses parallel resistance to distribute battery power between coils simultaneously, allowing vapor from multiple cartridges to mix during each draw.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity borosilicate glass reservoir</li>
<li>CCELL EVO Atomizer Platform</li>
<li>Patented stackable design — blend strains by stacking cartridges</li>
<li>510-thread metal mouthpiece connector</li>
<li>Linear Coupling Technology with parallel resistance</li>
<li>Snap-fit or press-fit closure</li>
<li>Compatible with most 510 cartridges and batteries</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 0.5ml</li>
<li>Body: Borosilicate glass</li>
<li>Heating: CCELL EVO Atomizer Platform</li>
<li>Thread: 510</li>
<li>Closure: Snap-fit or press-fit</li>
</ul>';

$thredz_short_05 = 'CCELL THREDZ 0.5ml stackable glass cartridge with EVO Atomizer Platform. First patented stackable 510 cartridge — blend strains by stacking.';
$thredz_meta_05 = 'CCELL THREDZ 0.5ml stackable 510 cartridge with EVO heating. Patented strain-blending design. Wholesale from Hamilton Devices.';

$thredz_desc_10 = 'The CCELL THREDZ is the first patented stackable 510-thread cartridge. The 1.0ml borosilicate glass reservoir pairs with the CCELL EVO Atomizer Platform and a threaded metal mouthpiece connector that enables stacking multiple cartridges for strain blending.

The Linear Coupling Technology uses parallel resistance to distribute battery power between coils simultaneously, allowing vapor from multiple cartridges to mix during each draw.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity borosilicate glass reservoir</li>
<li>CCELL EVO Atomizer Platform</li>
<li>Patented stackable design — blend strains by stacking cartridges</li>
<li>510-thread metal mouthpiece connector</li>
<li>Linear Coupling Technology with parallel resistance</li>
<li>Snap-fit or press-fit closure</li>
<li>Compatible with most 510 cartridges and batteries</li>
</ul>

<strong>Specifications:</strong>
<ul>
<li>Capacity: 1.0ml</li>
<li>Body: Borosilicate glass</li>
<li>Heating: CCELL EVO Atomizer Platform</li>
<li>Thread: 510</li>
<li>Closure: Snap-fit or press-fit</li>
</ul>';

$thredz_short_10 = 'CCELL THREDZ 1.0ml stackable glass cartridge with EVO Atomizer Platform. First patented stackable 510 cartridge — blend strains.';
$thredz_meta_10 = 'CCELL THREDZ 1.0ml stackable 510 cartridge with EVO heating. Patented strain-blending design. Wholesale from Hamilton Devices.';

// Update THREDZ 0.5ml (243016)
$now = date('Y-m-d H:i:s');
$now_gmt = gmdate('Y-m-d H:i:s');
$stmt = $conn->prepare("UPDATE wp_posts SET post_content=?, post_excerpt=?, post_modified=?, post_modified_gmt=? WHERE ID=243016");
$stmt->bind_param('ssss', $thredz_desc_05, $thredz_short_05, $now, $now_gmt);
$stmt->execute();
$conn->query("UPDATE wp_postmeta SET meta_value='$thredz_meta_05' WHERE post_id=243016 AND meta_key='_yoast_wpseo_metadesc'");
echo "  Updated THREDZ 0.5ml descriptions\n";

// Update THREDZ 1.0ml (243017)
$stmt2 = $conn->prepare("UPDATE wp_posts SET post_content=?, post_excerpt=?, post_modified=?, post_modified_gmt=? WHERE ID=243017");
$stmt2->bind_param('ssss', $thredz_desc_10, $thredz_short_10, $now, $now_gmt);
$stmt2->execute();
$conn->query("UPDATE wp_postmeta SET meta_value='$thredz_meta_10' WHERE post_id=243017 AND meta_key='_yoast_wpseo_metadesc'");
echo "  Updated THREDZ 1.0ml descriptions\n";

// =============================================================================
// STEP 2: Recreate products with correct data from Jupiter
// =============================================================================

echo "\n── Step 2: Creating corrected products ──\n\n";

$products = [

    // ── Blade — AIO, 3.0 Bio-Heating, 1.0mL/2.0mL ──
    array_merge($AIO_PRICING, [
        'title'       => 'CCELL Blade — 1.0ml All-In-One Disposable | 3.0 Bio-Heating',
        'slug'        => 'ccell-blade-1-0ml-aio-disposable',
        'sku'         => 'BLADE-10-AIO',
        'categories'  => [$tt_disposable, $tt_ccell, $tt_aio_bio],
        'description' => 'The CCELL Blade is a next-generation all-in-one disposable engineered for premium oil performance in an ultra-slim form factor. Featuring CCELL 3.0 Bio-Heating with VeinMesh design and 3D Stomata ceramic core, the Blade delivers smooth, flavorful vapor with 30% lower atomization temperatures to preserve terpenes.

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
</ul>',
        'short_desc'  => 'CCELL Blade 1.0ml AIO disposable with 3.0 Bio-Heating. Ultra-slim, VeinMesh element, 360-degree oil visibility, USB-C rechargeable.',
        'meta_desc'   => 'CCELL Blade 1.0ml all-in-one disposable with 3.0 Bio-Heating. Ultra-slim, VeinMesh + Stomata Core. Wholesale from Hamilton Devices.',
    ]),

    // ── Blade 2.0ml ──
    array_merge($AIO_PRICING, [
        'title'       => 'CCELL Blade — 2.0ml All-In-One Disposable | 3.0 Bio-Heating',
        'slug'        => 'ccell-blade-2-0ml-aio-disposable',
        'sku'         => 'BLADE-20-AIO',
        'categories'  => [$tt_disposable, $tt_ccell, $tt_aio_bio],
        'description' => 'The CCELL Blade 2.0ml is a next-generation all-in-one disposable with CCELL 3.0 Bio-Heating technology. The larger 2.0ml capacity and ultra-slim form factor (92.3 x 30 x 12.5 mm) make it ideal for brands offering larger-format disposables.

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
</ul>',
        'short_desc'  => 'CCELL Blade 2.0ml AIO disposable with 3.0 Bio-Heating. Ultra-slim, VeinMesh + Stomata Core, USB-C.',
        'meta_desc'   => 'CCELL Blade 2.0ml all-in-one disposable with 3.0 Bio-Heating. Wholesale from Hamilton Devices.',
    ]),

    // ── TurBoom — AIO, EVOMAX, dual-core, 2.0mL ──
    array_merge($AIO_PRICING, [
        'title'       => 'CCELL TurBoom — 2.0ml Dual-Core All-In-One | EVOMAX',
        'slug'        => 'ccell-turboom-2-0ml-aio-disposable',
        'sku'         => 'TURBOOM-20-AIO',
        'categories'  => [$tt_disposable, $tt_ccell, $tt_aio_evomax],
        'description' => 'The CCELL TurBoom is a high-performance dual-core all-in-one disposable featuring EVOMAX heating technology. Inspired by twin-engine aircraft design, its single-tank dual-core architecture delivers up to 16W of output power — 2-3x more cannabinoid delivery per puff than standard devices (8-10mg per puff).

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
</ul>',
        'short_desc'  => 'CCELL TurBoom 2.0ml dual-core AIO with EVOMAX heating. Up to 16W, 3 power modes, smart display, 500mAh USB-C battery.',
        'meta_desc'   => 'CCELL TurBoom 2.0ml dual-core all-in-one disposable with EVOMAX. Up to 16W, 3 power modes. Wholesale from Hamilton Devices.',
    ]),

    // ── Flexcell X — AIO, EVOMAX ──
    array_merge($AIO_PRICING, [
        'title'       => 'CCELL Flexcell X — All-In-One Disposable | EVOMAX',
        'slug'        => 'ccell-flexcell-x-aio-disposable',
        'sku'         => 'FLEXCELLX-AIO-EM',
        'categories'  => [$tt_disposable, $tt_ccell, $tt_aio_evomax],
        'description' => 'The CCELL Flexcell X is a clog-free all-in-one disposable featuring the EVOMAX ceramic core. Available in 0.5ml and 1.0-2.0ml capacities, it delivers unmatched flavor and compatibility from distillates to live rosin.

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
</ul>',
        'short_desc'  => 'CCELL Flexcell X AIO disposable with EVOMAX ceramic core. Clog-free, all oil types, 280mAh USB-C battery.',
        'meta_desc'   => 'CCELL Flexcell X all-in-one disposable with EVOMAX ceramic. Clog-free, all oils. Wholesale from Hamilton Devices.',
    ]),

    // ── Infinity — AIO, EVO (not EVO MAX!) ──
    array_merge($AIO_PRICING, [
        'title'       => 'CCELL Infinity — 1.0ml All-In-One Disposable | EVO',
        'slug'        => 'ccell-infinity-1-0ml-aio-disposable',
        'sku'         => 'INFINITY-10-AIO',
        'categories'  => [$tt_disposable, $tt_ccell, $tt_aio_se],
        'description' => 'The CCELL Infinity is a sleek, one-piece all-in-one disposable featuring EVO heating technology. The minimal design with fully customizable branding makes it an affordable yet premium solution for brands.

The EVO platform is an evolution of the SE — a larger yet thinner ceramic element with precision pore sizes delivers faster heating, improved flavor, and bigger clouds compared to SE.

<strong>Key Features:</strong>
<ul>
<li>1.0ml reservoir capacity</li>
<li>CCELL EVO Atomizer Platform</li>
<li>210mAh rechargeable battery</li>
<li>Micro-USB charging</li>
<li>Inhale-activated</li>
<li>One-piece minimal design</li>
<li>Fully customizable wrap/branding</li>
<li>Dimensions: 79 x 36 x 13 mm</li>
</ul>',
        'short_desc'  => 'CCELL Infinity 1.0ml AIO disposable with EVO heating. Sleek one-piece design, fully customizable branding.',
        'meta_desc'   => 'CCELL Infinity 1.0ml all-in-one disposable with EVO heating technology. Wholesale from Hamilton Devices.',
    ]),

    // ── Liquid X Glass — AIO (DS01 pen), SE or EVOMAX ──
    array_merge($AIO_PRICING, [
        'title'       => 'CCELL Liquid X Glass — Classic Pen-Style All-In-One | SE/EVOMAX',
        'slug'        => 'ccell-liquid-x-glass-aio-disposable',
        'sku'         => 'LXG-AIO',
        'categories'  => [$tt_disposable, $tt_ccell, $tt_aio_evomax],
        'description' => 'The CCELL Liquid X Glass (DS01) is a classic pen-style all-in-one disposable combining a visible glass oil tank with stainless steel housing. Available in 0.5ml and 1.0ml with your choice of SE or EVOMAX atomizer.

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
        'short_desc'  => 'CCELL Liquid X Glass pen-style AIO with SE or EVOMAX atomizer. Glass tank, stainless steel housing, USB-C.',
        'meta_desc'   => 'CCELL Liquid X Glass (DS01) pen-style all-in-one disposable. SE or EVOMAX atomizer. Wholesale from Hamilton Devices.',
    ]),

    // ── Airone — AIO, 3.0 Bio-Heating (NOT EVO MAX) ──
    array_merge($AIO_PRICING, [
        'title'       => 'CCELL Airone — All-In-One Disposable | 3.0 Bio-Heating',
        'slug'        => 'ccell-airone-aio-disposable',
        'sku'         => 'AIRONE-AIO-30',
        'categories'  => [$tt_disposable, $tt_ccell, $tt_aio_bio],
        'description' => 'The CCELL Airone is a refined, ultra-thin all-in-one disposable featuring CCELL 3.0 Bio-Heating with VeinMesh design. A crystal-clear viewing window, dual voltage settings, and clean airflow channels deliver precision performance.

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
</ul>',
        'short_desc'  => 'CCELL Airone AIO disposable with 3.0 Bio-Heating. Ultra-thin, dual voltage, VeinMesh + Stomata Core, USB-C.',
        'meta_desc'   => 'CCELL Airone all-in-one disposable with 3.0 Bio-Heating. Ultra-thin, dual voltage. Wholesale from Hamilton Devices.',
    ]),

    // ── Easy Pod — Pod System, EVOMAX (NOT SE) ──
    array_merge($POD_PRICING, [
        'title'       => 'CCELL Easy Pod — Pod System | EVOMAX Atomizer',
        'slug'        => 'ccell-easy-pod-system',
        'sku'         => 'EASYPOD-POD-EM',
        'categories'  => array_filter([$tt_pod, $tt_ccell]),
        'description' => 'The CCELL Easy Pod is Jupiter Research\'s most affordable pod system, featuring the EVOMAX Atomizer built to vaporize thick, high-viscosity extracts. A magnetic drop-in pod cartridge connects to the rechargeable power supply for easy cartridge swaps.

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
        'short_desc'  => 'CCELL Easy Pod system with EVOMAX Atomizer. Magnetic drop-in pods, 265mAh USB-C battery, handles live rosins and liquid diamonds.',
        'meta_desc'   => 'CCELL Easy Pod system with EVOMAX Atomizer. Magnetic pods, USB-C, high-viscosity support. Wholesale from Hamilton Devices.',
    ]),

    // ── Luster Pro — Pod System, SE Platform (NOT EVO MAX) ──
    array_merge($POD_PRICING, [
        'title'       => 'CCELL Luster Pro — Variable Wattage Pod System | SE Platform',
        'slug'        => 'ccell-luster-pro-pod-system',
        'sku'         => 'LUSTERPRO-POD-SE',
        'categories'  => array_filter([$tt_pod, $tt_ccell]),
        'description' => 'The CCELL Luster Pro is a variable wattage pod system featuring the SE Atomizer Platform, adjustable power settings, and a child-resistant button. Full metal construction with a 350mAh battery delivers premium build quality.

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
        'short_desc'  => 'CCELL Luster Pro variable wattage pod system with SE heating. 3 voltage settings, preheat, child-resistant, full metal.',
        'meta_desc'   => 'CCELL Luster Pro variable wattage pod system with SE heating. 350mAh, USB-C, child-resistant. Wholesale from Hamilton Devices.',
    ]),

    // ── Liquid Que — Pod System, SE Platform ──
    array_merge($POD_PRICING, [
        'title'       => 'CCELL Liquid Que — Pod System | SE Platform',
        'slug'        => 'ccell-liquid-que-pod-system',
        'sku'         => 'LIQUIDQUE-POD-SE',
        'categories'  => array_filter([$tt_pod, $tt_ccell]),
        'description' => 'The CCELL Liquid Que is a premium pod vaporizer featuring the SE Atomizer Platform and anodized metal construction. The snap-in pod cartridge with magnetic connection delivers consistent vapor production for distillate formulations.

<strong>Key Features:</strong>
<ul>
<li>0.5ml or 1.0ml magnetic drop-in pod cartridge</li>
<li>CCELL SE Atomizer Platform</li>
<li>330mAh rechargeable battery</li>
<li>Micro-USB charging</li>
<li>Inhale-activated</li>
<li>Anodized metal construction</li>
<li>Magnetic pod connection</li>
<li>Discreet, compact design</li>
<li>LED indicator</li>
<li>Dimensions: 66.5 x 26.5 x 12 mm</li>
</ul>',
        'short_desc'  => 'CCELL Liquid Que pod system with SE heating. Anodized metal, magnetic pods, 330mAh battery.',
        'meta_desc'   => 'CCELL Liquid Que pod system with SE heating. Anodized metal, magnetic pods. Wholesale from Hamilton Devices.',
    ]),

    // ── Kap — 510 Power Supply (NOT pod system) ──
    array_merge($BATTERY_PRICING, [
        'title'       => 'CCELL Kap — 510 Thread Power Supply | Variable Voltage',
        'slug'        => 'ccell-kap-510-power-supply',
        'sku'         => 'KAP-510-PS',
        'categories'  => [$tt_battery, $tt_ccell],
        'description' => 'The CCELL Kap is a compact 510-thread power supply with a 500mAh battery, three variable voltage settings, and a magnetic cartridge sleeve for secure, flush-fitting cartridges up to 14mm diameter.

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
        'short_desc'  => 'CCELL Kap 510 power supply. 500mAh, 3 voltage settings (2.6V/3.0V/3.4V), magnetic sleeve, 15s preheat, USB-C.',
        'meta_desc'   => 'CCELL Kap 510 power supply with variable voltage and magnetic cartridge sleeve. Wholesale from Hamilton Devices.',
    ]),
];

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

// Note: Klik is a dispenser (for RSO/CBD dosing), NOT a 510 battery — not recreating it
echo "\nNOTE: Klik is a cannabis oil dispenser (not a battery). Skipped.\n";

// =============================================================================
// STEP 3: Update term counts
// =============================================================================

echo "\n── Step 3: Updating term counts ──\n";

$all_tt = array_filter([
    $tt_cartridge, $tt_disposable, $tt_battery, $tt_ccell,
    $tt_evo_max, $tt_easy, $tt_ceramic, $tt_postless,
    $tt_aio_evomax, $tt_aio_se, $tt_aio_bio, $tt_pod,
]);

foreach ($all_tt as $tt_id) {
    $conn->query("UPDATE wp_term_taxonomy SET count = (SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = $tt_id) WHERE term_taxonomy_id = $tt_id");
    $r = $conn->query("SELECT t.name, tt.count FROM wp_term_taxonomy tt JOIN wp_terms t ON t.term_id=tt.term_id WHERE tt.term_taxonomy_id=$tt_id");
    $row = $r->fetch_assoc();
    if ($row) echo "  {$row['name']}: {$row['count']} products\n";
}

echo "\n=== Fix Complete ===\n";
echo "Deleted: " . count($delete_ids) . " incorrect products\n";
echo "Created: " . count($created_ids) . " corrected products\n";
echo "Skipped: $skip_count\n";
if ($created_ids) echo "New IDs: " . implode(', ', $created_ids) . "\n";
echo "\nWARNING: All pricing is PLACEHOLDER.\n";
echo "Done!\n";

$conn->close();
