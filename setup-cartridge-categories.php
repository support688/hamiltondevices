<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// Create new technology-based sub-categories under Cartridge (543)
// These will be used for organizing the visual selector

// First check what categories already exist
$existing = [];
$result = $conn->query("
    SELECT t.term_id, t.name, t.slug
    FROM wp_terms t
    JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
    WHERE tt.taxonomy = 'product_cat' AND tt.parent = 543
");
while ($row = $result->fetch_assoc()) {
    $existing[$row['slug']] = $row;
    echo "Existing: {$row['term_id']} - {$row['name']} ({$row['slug']})\n";
}

// Function to create a product category
function create_category($conn, $name, $slug, $parent, $description = '') {
    // Insert into wp_terms
    $stmt = $conn->prepare("INSERT INTO wp_terms (name, slug) VALUES (?, ?)");
    $stmt->bind_param('ss', $name, $slug);
    $stmt->execute();
    $term_id = $conn->insert_id;

    // Insert into wp_term_taxonomy
    $stmt2 = $conn->prepare("INSERT INTO wp_term_taxonomy (term_id, taxonomy, description, parent, count) VALUES (?, 'product_cat', ?, ?, 0)");
    $stmt2->bind_param('isi', $term_id, $description, $parent);
    $stmt2->execute();

    echo "Created category: $name (ID: $term_id, slug: $slug)\n";
    return $term_id;
}

// Create the 4 technology tier categories
$categories_to_create = [
    [
        'name' => 'CCELL Easy (EVO)',
        'slug' => 'ccell-easy',
        'desc' => 'Economy CCELL cartridges with the proven EVO heating coil. The best value option for high-volume programs. Available in glass (TH2) and polycarbonate (M6T) bodies.'
    ],
    [
        'name' => 'CCELL EVO MAX',
        'slug' => 'ccell-evo-max',
        'desc' => 'Premium CCELL cartridges featuring the advanced EVO MAX heating coil for superior vapor quality and consistency. Available in glass (TH2) and polycarbonate (M6T) bodies.'
    ],
    [
        'name' => 'CCELL Ceramic EVO MAX',
        'slug' => 'ccell-ceramic-evo-max',
        'desc' => 'Premium ceramic-body cartridges with EVO MAX heating technology. Eliminates metal contact with oil for the purest flavor profile.'
    ],
    [
        'name' => 'CCELL 3.0 Postless',
        'slug' => 'ccell-3-postless',
        'desc' => 'Next-generation postless cartridges featuring CCELL 3.0 heating technology. The Klean series offers the cleanest oil path and easiest filling process.'
    ],
];

$new_cat_ids = [];
foreach ($categories_to_create as $cat) {
    if (isset($existing[$cat['slug']])) {
        echo "Already exists: {$cat['name']} ({$existing[$cat['slug']]['term_id']})\n";
        $new_cat_ids[$cat['slug']] = $existing[$cat['slug']]['term_id'];
    } else {
        $new_cat_ids[$cat['slug']] = create_category($conn, $cat['name'], $cat['slug'], 543, $cat['desc']);
    }
}

echo "\nNew category IDs:\n";
echo json_encode($new_cat_ids, JSON_PRETTY_PRINT) . "\n";

// Now assign existing products to the correct technology categories
// EVO MAX products (M6T-EVO and TH2-EVO lines)
$evo_max_products = [
    221083, 221082, 221078, 221074, // M6T05-EVO 0.5ml variants
    221032, 220993, 220987,          // M6T10-EVO 1.0ml variants
    179667, 179662,                   // M6T05 EVO 0.5ml
];

// 3.0 Postless (Klean line)
$postless_products = [
    196993, 196978, 192435,  // Klean White, Klean SS, Klean Black
];

// Kera and ZICO - these are older/special products, put in EVO MAX or leave in parent
$other_products = [
    171374,  // Kera (EVO MAX ceramic predecessor?)
    159770, 159762,  // ZICO bottom fill (special)
    230111,  // M6T05-SE (Special Edition)
];

// Easy products - the M6T-S (standard/easy) line
$easy_products = [
    221071, 221070, 221066, 221062,  // M6T05-S 0.5ml variants
];

// Assign EVO MAX products
if (isset($new_cat_ids['ccell-evo-max'])) {
    $evo_max_tax_id = null;
    $r = $conn->query("SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE term_id = {$new_cat_ids['ccell-evo-max']} AND taxonomy = 'product_cat'");
    $row = $r->fetch_assoc();
    $evo_max_tax_id = $row['term_taxonomy_id'];

    foreach ($evo_max_products as $pid) {
        $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES ($pid, $evo_max_tax_id)");
    }
    // Update count
    $conn->query("UPDATE wp_term_taxonomy SET count = " . count($evo_max_products) . " WHERE term_taxonomy_id = $evo_max_tax_id");
    echo "Assigned " . count($evo_max_products) . " products to CCELL EVO MAX\n";
}

// Assign Postless products
if (isset($new_cat_ids['ccell-3-postless'])) {
    $r = $conn->query("SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE term_id = {$new_cat_ids['ccell-3-postless']} AND taxonomy = 'product_cat'");
    $row = $r->fetch_assoc();
    $postless_tax_id = $row['term_taxonomy_id'];

    foreach ($postless_products as $pid) {
        $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES ($pid, $postless_tax_id)");
    }
    $conn->query("UPDATE wp_term_taxonomy SET count = " . count($postless_products) . " WHERE term_taxonomy_id = $postless_tax_id");
    echo "Assigned " . count($postless_products) . " products to CCELL 3.0 Postless\n";
}

// Assign Easy products
if (isset($new_cat_ids['ccell-easy'])) {
    $r = $conn->query("SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE term_id = {$new_cat_ids['ccell-easy']} AND taxonomy = 'product_cat'");
    $row = $r->fetch_assoc();
    $easy_tax_id = $row['term_taxonomy_id'];

    foreach ($easy_products as $pid) {
        $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES ($pid, $easy_tax_id)");
    }
    $conn->query("UPDATE wp_term_taxonomy SET count = " . count($easy_products) . " WHERE term_taxonomy_id = $easy_tax_id");
    echo "Assigned " . count($easy_products) . " products to CCELL Easy\n";
}

echo "\nDone setting up categories!\n";
$conn->close();
