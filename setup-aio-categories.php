<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

$parent_tt = 550; // CCELL Disposables term_taxonomy_id

// Get parent term_taxonomy_id
$r = $conn->query("SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE term_id=550 AND taxonomy='product_cat'");
$parent_tt_id = $r->fetch_assoc()['term_taxonomy_id'];
echo "Parent TT ID: $parent_tt_id\n";

// Create 3 technology subcategories
$cats = [
    ['name' => 'AIO SE (Standard)', 'slug' => 'aio-se-standard', 'desc' => 'Economy AIO disposables with proven SE ceramic heating. Best for distillate programs.'],
    ['name' => 'AIO EVO MAX', 'slug' => 'aio-evo-max', 'desc' => 'Premium all-oil AIO disposables with EVO MAX heating. Works with every oil type.'],
    ['name' => 'AIO 3.0 Bio-Heating', 'slug' => 'aio-3-bio-heating', 'desc' => 'Next-generation AIO disposables with CCELL 3.0 Bio-Heating and postless design.'],
];

$cat_ids = [];
foreach ($cats as $cat) {
    // Insert term
    $stmt = $conn->prepare("INSERT INTO wp_terms (name, slug, term_group) VALUES (?, ?, 0)");
    $stmt->bind_param('ss', $cat['name'], $cat['slug']);
    $stmt->execute();
    $term_id = $conn->insert_id;

    // Insert term_taxonomy
    $stmt2 = $conn->prepare("INSERT INTO wp_term_taxonomy (term_id, taxonomy, description, parent, count) VALUES (?, 'product_cat', ?, 550, 0)");
    $stmt2->bind_param('is', $term_id, $cat['desc']);
    $stmt2->execute();
    $tt_id = $conn->insert_id;

    echo "Created: {$cat['name']} (term_id=$term_id, tt_id=$tt_id)\n";
    $cat_ids[$cat['slug']] = $tt_id;
}

// Assign existing products to tiers
// SE tier: DS01 series, Listo, Voca, Skye II, Ridge, Eazie, Flex, Slym, Memento, OWA, Poche, DS1903, TH001, Blanc
$se_products = [];
$se_terms = ['Eazie', 'Listo', 'Voca 1.0ml', 'Skye', 'Ridge', 'DS0110', 'DS1903', 'Flex 1.0ml', 'Flex 2.0ml', 'Flex Pro', 'Slym', 'Memento', 'OWA', 'Poche', 'TH001', 'Blanc', 'Sima'];
$result = $conn->query("SELECT p.ID, p.post_title FROM wp_posts p JOIN wp_term_relationships tr ON p.ID=tr.object_id JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id=tt.term_taxonomy_id WHERE tt.term_id=550 AND tt.taxonomy='product_cat' AND p.post_type='product' AND p.post_status='publish'");
while ($row = $result->fetch_assoc()) {
    $title = $row['post_title'];
    $id = $row['ID'];
    $assigned = false;

    // 3.0 Bio-Heating: GemBar, GemBox, MixJoy
    if (preg_match('/Gem\s?Bar|GemBox|MixJoy/i', $title)) {
        $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($id, {$cat_ids['aio-3-bio-heating']}, 0)");
        echo "  3.0: $title\n";
        $assigned = true;
    }
    // EVO MAX: Tank, Mini Tank, Voca Max, Voca Pro Max, Rosin Bar
    elseif (preg_match('/\bTank\b|Voca Max|Voca Pro Max|Rosin Bar/i', $title)) {
        $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($id, {$cat_ids['aio-evo-max']}, 0)");
        echo "  EVO MAX: $title\n";
        $assigned = true;
    }
    // SE: everything else
    else {
        $conn->query("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES ($id, {$cat_ids['aio-se-standard']}, 0)");
        echo "  SE: $title\n";
        $assigned = true;
    }
}

// Update counts
foreach ($cat_ids as $slug => $tt_id) {
    $conn->query("UPDATE wp_term_taxonomy SET count = (SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id=$tt_id) WHERE term_taxonomy_id=$tt_id");
    $count = $conn->query("SELECT count FROM wp_term_taxonomy WHERE term_taxonomy_id=$tt_id")->fetch_assoc()['count'];
    echo "Category $slug: $count products\n";
}

echo "\nDone! Category TT IDs:\n";
foreach ($cat_ids as $slug => $tt_id) {
    echo "  $slug => $tt_id\n";
}

$conn->close();
