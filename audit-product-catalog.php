<?php
/**
 * Audit Product Catalog
 *
 * Checks every CCELL product for:
 * - Technology subcategory assignment
 * - Accurate term counts
 * - Missing descriptions, pricing, or images
 * - Orphaned products not in any subcategory
 *
 * Run via: docker compose exec -T wordpress php < audit-product-catalog.php
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Product Catalog Audit ===\n\n";

// =============================================================================
// 1. Gather all technology subcategory slugs and their TT IDs
// =============================================================================

$tech_slugs = [
    'ccell-evo-max', 'ccell-easy', 'ccell-ceramic-evo-max', 'ccell-3-postless',
    'aio-evo-max', 'aio-se-standard', 'aio-3-bio-heating',
];

$tech_tt_ids = [];
foreach ($tech_slugs as $slug) {
    $r = $conn->query("
        SELECT tt.term_taxonomy_id, t.name
        FROM wp_term_taxonomy tt
        JOIN wp_terms t ON t.term_id = tt.term_id
        WHERE t.slug = '$slug' AND tt.taxonomy = 'product_cat'
        LIMIT 1
    ");
    $row = $r->fetch_assoc();
    if ($row) {
        $tech_tt_ids[$slug] = $row['term_taxonomy_id'];
        echo "  Found: {$row['name']} (slug=$slug, tt_id={$row['term_taxonomy_id']})\n";
    } else {
        echo "  WARNING: Technology category '$slug' not found!\n";
    }
}
echo "\n";

// =============================================================================
// 2. Get all published products in CCELL parent categories
// =============================================================================

// Parent categories: Cartridge=543, Disposable=550, Battery=542
$parent_cats = [543, 550, 542];

// Also include pod category if it exists
$pod_check = $conn->query("SELECT term_id FROM wp_terms WHERE slug='pod-systems' LIMIT 1");
$pod_row = $pod_check->fetch_assoc();
if ($pod_row) {
    $parent_cats[] = $pod_row['term_id'];
}
// Also try term_id 1050
$pod_check2 = $conn->query("SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE term_id=1050 AND taxonomy='product_cat' LIMIT 1");
if ($pod_check2->num_rows > 0) {
    $parent_cats[] = 1050;
}

$parent_cats = array_unique($parent_cats);
$parent_tt_ids = [];
foreach ($parent_cats as $cat_id) {
    $r = $conn->query("SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE term_id=$cat_id AND taxonomy='product_cat'");
    $row = $r->fetch_assoc();
    if ($row) $parent_tt_ids[] = $row['term_taxonomy_id'];
}

if (empty($parent_tt_ids)) {
    die("ERROR: No parent category TT IDs found.\n");
}

$tt_list = implode(',', $parent_tt_ids);
$result = $conn->query("
    SELECT DISTINCT p.ID, p.post_title, p.post_content, p.post_excerpt, p.post_status
    FROM wp_posts p
    JOIN wp_term_relationships tr ON p.ID = tr.object_id
    WHERE tr.term_taxonomy_id IN ($tt_list)
      AND p.post_type = 'product'
      AND p.post_status IN ('publish', 'draft', 'private')
    ORDER BY p.ID ASC
");

$all_products = [];
while ($row = $result->fetch_assoc()) {
    $all_products[$row['ID']] = $row;
}

echo "Found " . count($all_products) . " products in CCELL categories\n\n";

// =============================================================================
// 3. Check each product
// =============================================================================

$issues = [
    'no_subcategory'   => [],
    'no_description'   => [],
    'no_short_desc'    => [],
    'no_price'         => [],
    'no_image'         => [],
    'no_seo'           => [],
    'not_published'    => [],
];

foreach ($all_products as $pid => $prod) {
    // Check technology subcategory
    $has_subcat = false;
    foreach ($tech_tt_ids as $slug => $tt_id) {
        $check = $conn->query("SELECT 1 FROM wp_term_relationships WHERE object_id=$pid AND term_taxonomy_id=$tt_id LIMIT 1");
        if ($check->num_rows > 0) {
            $has_subcat = true;
            break;
        }
    }
    // Also check battery/pod parent categories (they use fallback in the theme)
    if (!$has_subcat) {
        foreach ([542, 1050] as $fallback_cat) {
            $r = $conn->query("
                SELECT 1 FROM wp_term_relationships tr
                JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tr.object_id = $pid AND tt.term_id = $fallback_cat AND tt.taxonomy = 'product_cat'
                LIMIT 1
            ");
            if ($r->num_rows > 0) {
                $has_subcat = true;
                break;
            }
        }
    }
    if (!$has_subcat) {
        $issues['no_subcategory'][] = $pid;
    }

    // Check description
    if (empty(trim($prod['post_content']))) {
        $issues['no_description'][] = $pid;
    }

    // Check short description
    if (empty(trim($prod['post_excerpt']))) {
        $issues['no_short_desc'][] = $pid;
    }

    // Check price
    $price_check = $conn->query("SELECT meta_value FROM wp_postmeta WHERE post_id=$pid AND meta_key='_price' LIMIT 1");
    $price_row = $price_check->fetch_assoc();
    if (!$price_row || empty($price_row['meta_value'])) {
        $issues['no_price'][] = $pid;
    }

    // Check thumbnail image
    $img_check = $conn->query("SELECT meta_value FROM wp_postmeta WHERE post_id=$pid AND meta_key='_thumbnail_id' LIMIT 1");
    $img_row = $img_check->fetch_assoc();
    if (!$img_row || empty($img_row['meta_value']) || $img_row['meta_value'] === '0') {
        $issues['no_image'][] = $pid;
    }

    // Check Yoast SEO meta description
    $seo_check = $conn->query("SELECT meta_value FROM wp_postmeta WHERE post_id=$pid AND meta_key='_yoast_wpseo_metadesc' LIMIT 1");
    $seo_row = $seo_check->fetch_assoc();
    if (!$seo_row || empty(trim($seo_row['meta_value']))) {
        $issues['no_seo'][] = $pid;
    }

    // Check publish status
    if ($prod['post_status'] !== 'publish') {
        $issues['not_published'][] = $pid;
    }
}

// =============================================================================
// 4. Report issues
// =============================================================================

echo "── Issue Report ──\n\n";

$issue_labels = [
    'no_subcategory'  => 'Missing technology subcategory',
    'no_description'  => 'Missing product description (post_content)',
    'no_short_desc'   => 'Missing short description (post_excerpt)',
    'no_price'        => 'Missing price (_price meta)',
    'no_image'        => 'Missing thumbnail image (_thumbnail_id)',
    'no_seo'          => 'Missing Yoast SEO meta description',
    'not_published'   => 'Not published (draft/private)',
];

$total_issues = 0;
foreach ($issues as $key => $pids) {
    $count = count($pids);
    $total_issues += $count;
    $label = $issue_labels[$key];

    if ($count > 0) {
        echo "[$count] $label:\n";
        foreach ($pids as $pid) {
            $title = $all_products[$pid]['post_title'] ?? 'Unknown';
            echo "  - ID $pid: $title\n";
        }
        echo "\n";
    } else {
        echo "[0] $label: All clear!\n";
    }
}

// =============================================================================
// 5. Verify and fix term counts
// =============================================================================

echo "\n── Term Count Verification ──\n\n";

$all_cats_to_check = array_merge($parent_tt_ids, array_values($tech_tt_ids));
$all_cats_to_check = array_unique($all_cats_to_check);

$count_fixes = 0;
foreach ($all_cats_to_check as $tt_id) {
    // Get current stored count
    $r = $conn->query("SELECT t.name, tt.count FROM wp_term_taxonomy tt JOIN wp_terms t ON t.term_id = tt.term_id WHERE tt.term_taxonomy_id = $tt_id");
    $row = $r->fetch_assoc();
    if (!$row) continue;

    // Get actual count
    $actual = $conn->query("SELECT COUNT(*) as cnt FROM wp_term_relationships WHERE term_taxonomy_id = $tt_id")->fetch_assoc()['cnt'];

    $status = ($row['count'] == $actual) ? 'OK' : 'FIXED';
    if ($row['count'] != $actual) {
        $conn->query("UPDATE wp_term_taxonomy SET count = $actual WHERE term_taxonomy_id = $tt_id");
        $count_fixes++;
    }

    echo "  {$row['name']} (tt=$tt_id): stored={$row['count']}, actual=$actual [$status]\n";
}

// =============================================================================
// 6. Summary
// =============================================================================

echo "\n── Summary ──\n";
echo "Total products audited: " . count($all_products) . "\n";
echo "Total issues found: $total_issues\n";
echo "Term count fixes: $count_fixes\n";

if ($total_issues === 0 && $count_fixes === 0) {
    echo "\nAll products pass audit! Catalog is clean.\n";
} else {
    echo "\nReview issues above and run the appropriate fix scripts.\n";
}

echo "\nDone!\n";
$conn->close();
