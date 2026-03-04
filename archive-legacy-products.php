<?php
/**
 * Archive legacy products to "Classics" category
 *
 * Products NOT in Jupiter's current catalog get:
 *   1. Added to a new "Classics" category
 *   2. Removed from main parent + tech subcategories (so term counts are accurate)
 *   3. WooCommerce visibility set to "hidden" (direct URL still works, but won't
 *      appear in shop, category pages, or search — SEO preserved)
 *
 * Run via: docker compose exec -T wordpress php < archive-legacy-products.php
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Archive Legacy Products to Classics ===\n\n";

// =============================================================================
// Step 1: Create "Classics" category
// =============================================================================

echo "── Step 1: Create Classics category ──\n";

$classics_slug = 'ccell-classics';
$r = $conn->query("SELECT term_id FROM wp_terms WHERE slug='$classics_slug' LIMIT 1");
$row = $r->fetch_assoc();

if ($row) {
    $classics_term_id = $row['term_id'];
    echo "  Classics category already exists (term_id: $classics_term_id)\n";
} else {
    $conn->query("INSERT INTO wp_terms (name, slug, term_group) VALUES ('CCELL Classics', '$classics_slug', 0)");
    $classics_term_id = $conn->insert_id;
    $conn->query("INSERT INTO wp_term_taxonomy (term_id, taxonomy, description, parent, count)
        VALUES ($classics_term_id, 'product_cat', 'Legacy and discontinued CCELL products. These products are no longer in active production but pages are preserved for reference.', 0, 0)");
    echo "  Created Classics category (term_id: $classics_term_id)\n";
}

$r2 = $conn->query("SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE term_id=$classics_term_id AND taxonomy='product_cat'");
$tt_classics = $r2->fetch_assoc()['term_taxonomy_id'];
echo "  Classics TT ID: $tt_classics\n\n";

// =============================================================================
// Step 2: Define products to archive
// Cross-referenced against Jupiter Research catalog (March 2026)
// =============================================================================

// Products NOT on Jupiter's current site → move to Classics
$archive_products = [

    // ── CARTRIDGES not on Jupiter ──
    159762,  // CCELL ZICO 0.5ml Bottom Fill — discontinued
    159770,  // CCELL ZICO 1.0ml Bottom Fill — discontinued
    28104,   // CCELL® Palm Kit (Blue) — old combo/bundle
    28099,   // Five Pack — old bundle
    28094,   // Ten Pack — old bundle
    192435,  // CCELL® Klean Black — not on Jupiter
    196978,  // CCELL® Klean Stainless Steel — not on Jupiter
    196993,  // CCELL® Klean White — not on Jupiter

    // ── DISPOSABLES not on Jupiter ──
    166685,  // CCELL Eazie 0.3ml — discontinued
    163263,  // CCELL Sima 1.0ml — discontinued
    123735,  // CCELL® DS1903-M 0.3ml — discontinued
    123740,  // CCELL® DS1903-U 0.3ml — discontinued
    143123,  // CCELL® Poche 0.5ml — discontinued
    143139,  // CCELL® Memento 0.3ml — discontinued
    143130,  // CCELL® Slym 0.3ml — Jupiter only lists 0.5ml+
    165694,  // CCELL® OWA 0.5ml — discontinued
    161901,  // Skye II 0.5ml — discontinued
    162487,  // Skye II 1.0ml — discontinued
    114822,  // CCELL® TH001 — very old, discontinued
    // DS0110-US mouthpiece variants — replaced by Liquid X Glass (243042)
    179872,  // DS0110-US Black Plastic Round
    200400,  // DS0110-US Black Plastic Flat
    200405,  // DS0110-US White Plastic Flat
    200410,  // DS0110-US Black Ceramic
    200415,  // DS0110-US White Ceramic
    200420,  // DS0110-US Chrome Flat
    200430,  // DS0110-US Chrome Round Fluted
    200435,  // DS0110-US Sandalwood Flat
    200440,  // DS0110-US Sandalwood Round
    200445,  // DS0110-US Stainless Steel

    // ── BATTERIES not on Jupiter (Hamilton originals + discontinued CCELL) ──
    179792,  // Butterfly — Hamilton original
    190313,  // Sandwave — Hamilton original
    28239,   // M3 (Black, White, Red) — old gen
    28070,   // M3 (Gold) — old gen
    38716,   // M3 (Rainbow) — old gen
    28122,   // M3 (Rose Gold) — old gen
    27860,   // M3 (Stainless Steel) — old gen
    63349,   // M3B — replaced by M3B Plus
    161932,  // M3 Plus — older branding
    // Palm Battery colors — replaced by Palm SE / Palm Pro
    149983,  // Palm (Black/Yellow)
    35981,   // Palm (Blue/Light Brown)
    149975,  // Palm (Gray/Orange)
    35990,   // Palm (Green/Rose Gold)
    137917,  // Palm (Purple/Gold)
    149985,  // Palm (Red/Blue)
    149984,  // Palm (Rose Gold/Pink)
    137923,  // Palm (Yellow/Purple)
    // Silo Battery variants — discontinued
    28182,   // Silo (Black)
    28191,   // Silo (Blue)
    40952,   // Silo (Gold)
    28198,   // Silo (Gray)
    28204,   // Silo (Pink)
    40960,   // Silo (Red)
    // Hamilton original batteries
    75657,   // Cloak™
    71048,   // Cube™
    146959,  // Gamer
    60257,   // Gold Bar
    185968,  // Jetstream
    230709,  // Jetstream & M3 Plus Combo
    215839,  // Jetstream + Concentrate Kit Combo
    206919,  // Jetstream Mini
    89145,   // KR1
    230105,  // M6T 0.5 SE + M3 Battery Combo
    154783,  // Nomad
    103862,  // PB1 Pipe Battery
    89153,   // PS1
    161923,  // RIZO
    147641,  // Starship Vape
    140678,  // THE SHIV
    43853,   // Tombstone™

    // ── PODS not on Jupiter ──
    64150,   // Uno Battery — discontinued
    64157,   // Uno Pod — discontinued
    65375,   // Luster Battery (original, not Luster Pro) — discontinued
    160169,  // Luster Pod 0.5ml (original) — discontinued
];

// Categories to remove products from (so term counts reflect only active products)
$remove_cats = [];
foreach ([543, 550, 542, 1050, 1234] as $tid) {
    $r = $conn->query("SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE term_id=$tid AND taxonomy='product_cat'");
    $row = $r->fetch_assoc();
    if ($row) $remove_cats[$tid] = $row['term_taxonomy_id'];
}

// Tech subcategory TT IDs
$tech_slugs = ['ccell-evo-max','ccell-easy','ccell-ceramic-evo-max','ccell-3-postless',
               'aio-evo-max','aio-se-standard','aio-3-bio-heating'];
$tech_tt_ids = [];
foreach ($tech_slugs as $slug) {
    $r = $conn->query("SELECT tt.term_taxonomy_id FROM wp_term_taxonomy tt JOIN wp_terms t ON t.term_id=tt.term_id WHERE t.slug='$slug' AND tt.taxonomy='product_cat' LIMIT 1");
    $row = $r->fetch_assoc();
    if ($row) $tech_tt_ids[] = $row['term_taxonomy_id'];
}

// =============================================================================
// Step 3: Archive each product
// =============================================================================

echo "── Step 2: Archiving products ──\n\n";

$archived = 0;
$skipped = 0;

foreach ($archive_products as $pid) {
    $check = $conn->query("SELECT post_title, post_status FROM wp_posts WHERE ID=$pid AND post_type='product'");
    $prod = $check->fetch_assoc();
    if (!$prod) {
        echo "  SKIP: ID $pid not found\n";
        $skipped++;
        continue;
    }

    // Add to Classics category
    $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($pid, $tt_classics, 0)");

    // Remove from main parent categories and tech subcategories
    $all_remove = array_merge(array_values($remove_cats), $tech_tt_ids);
    foreach ($all_remove as $tt_id) {
        $conn->query("DELETE FROM wp_term_relationships WHERE object_id=$pid AND term_taxonomy_id=$tt_id");
    }

    // Set WooCommerce visibility to hidden
    $vis_check = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=$pid AND meta_key='_visibility' LIMIT 1");
    if ($vis_check->num_rows > 0) {
        $conn->query("UPDATE wp_postmeta SET meta_value='hidden' WHERE post_id=$pid AND meta_key='_visibility'");
    } else {
        $conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($pid, '_visibility', 'hidden')");
    }

    // Keep product published (URL stays live for SEO)
    if ($prod['post_status'] !== 'publish') {
        // Leave draft/private products as-is
    }

    echo "  Archived: {$prod['post_title']} (ID: $pid)\n";
    $archived++;
}

// =============================================================================
// Step 4: Update all term counts
// =============================================================================

echo "\n── Step 3: Updating term counts ──\n";

$all_tt_to_update = array_merge(
    array_values($remove_cats),
    $tech_tt_ids,
    [$tt_classics]
);
$all_tt_to_update = array_unique($all_tt_to_update);

foreach ($all_tt_to_update as $tt_id) {
    $conn->query("UPDATE wp_term_taxonomy SET count = (SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = $tt_id) WHERE term_taxonomy_id = $tt_id");
    $r = $conn->query("SELECT t.name, tt.count FROM wp_term_taxonomy tt JOIN wp_terms t ON t.term_id=tt.term_id WHERE tt.term_taxonomy_id=$tt_id");
    $row = $r->fetch_assoc();
    if ($row) echo "  {$row['name']}: {$row['count']} products\n";
}

// =============================================================================
// Summary
// =============================================================================

echo "\n=== Archive Complete ===\n";
echo "Archived: $archived products\n";
echo "Skipped: $skipped\n";
echo "\nWhat happened to each product:\n";
echo "  - Added to 'CCELL Classics' category\n";
echo "  - Removed from Cartridges/Disposables/Batteries/Pods categories\n";
echo "  - Removed from technology subcategories\n";
echo "  - WooCommerce visibility set to 'hidden'\n";
echo "  - Product URLs still work (SEO preserved)\n";
echo "  - Products still 'published' status (Google can still index)\n";
echo "\nDone!\n";

$conn->close();
