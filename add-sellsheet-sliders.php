<?php
/**
 * Add sell sheet images to Ceramic-EVOMAX and TH2-SE/M6T-SE products
 *
 * Single-page sell sheets — added as a full-width image (not a slider carousel).
 *
 * Run via: docker compose exec -T wordpress php < add-sellsheet-sliders.php
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Add Sell Sheet Images to Products ===\n\n";

$year_month = '2026/03';
$upload_dir = "/var/www/html/wp-content/uploads/$year_month";

// ── Helper: create attachment record ──
function create_attachment($conn, $filename, $title, $upload_dir, $year_month) {
    $local_path = "$upload_dir/$filename";
    $relative_path = "$year_month/$filename";

    if (!file_exists($local_path)) {
        echo "  NOT FOUND: $local_path\n";
        return false;
    }

    // Check if attachment already exists
    $check = $conn->prepare("SELECT p.ID FROM wp_posts p JOIN wp_postmeta pm ON p.ID=pm.post_id WHERE pm.meta_key='_wp_attached_file' AND pm.meta_value=? AND p.post_type='attachment' LIMIT 1");
    $check->bind_param('s', $relative_path);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    if ($row) return $row['ID'];

    $img_info = getimagesize($local_path);
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
        0, ?, 0, 'attachment', ?, 0
    )");
    $stmt->bind_param('ssssssss', $now, $now_gmt, $title, $slug, $now, $now_gmt, $guid, $mime_type);
    $stmt->execute();
    $attach_id = $conn->insert_id;
    if (!$attach_id) return false;

    $stmt2 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_wp_attached_file', ?)");
    $stmt2->bind_param('is', $attach_id, $relative_path);
    $stmt2->execute();

    $meta = serialize([
        'width' => $width, 'height' => $height, 'file' => $relative_path, 'sizes' => [],
        'image_meta' => ['aperture'=>'0','credit'=>'','camera'=>'','caption'=>'',
            'created_timestamp'=>'0','copyright'=>'','focal_length'=>'0',
            'iso'=>'0','shutter_speed'=>'0','title'=>'','orientation'=>'0','keywords'=>[]]
    ]);
    $stmt3 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_wp_attachment_metadata', ?)");
    $stmt3->bind_param('is', $attach_id, $meta);
    $stmt3->execute();

    return $attach_id;
}

// ── Sell sheet mappings ──
$sell_sheets = [
    [
        'filename' => 'ceramic-evomax-sellsheet-page-01.jpg',
        'label' => 'Ceramic-EVOMAX Sell Sheet',
        'title' => 'Ceramic-EVOMAX Sell Sheet',
        'product_ids' => [243173, 243174, 243175],
    ],
    [
        'filename' => 'th2se-m6tse-sellsheet-page-01.jpg',
        'label' => 'TH2-SE / M6T-SE Sell Sheet',
        'title' => 'TH2-SE M6T-SE Sell Sheet',
        'product_ids' => [
            // TH210-SE
            221042, 221044, 229958, 229965, 238452,
            // M6T10-SE
            221019, 221024, 221028, 218049,
            // M6T05-SE
            230111, 230105,
        ],
    ],
];

$total_products = 0;

foreach ($sell_sheets as $ss) {
    echo "══ {$ss['label']} ══\n";

    // Create attachment
    echo "  Creating attachment for {$ss['filename']}... ";
    $attach_id = create_attachment($conn, $ss['filename'], $ss['title'], $upload_dir, $year_month);
    if (!$attach_id) {
        echo "FAILED\n\n";
        continue;
    }
    echo "OK (ID: $attach_id)\n";

    // Build the showcase HTML — single image, not a slider
    $showcase = "\n\n<!-- Product Showcase -->\n";
    $showcase .= '<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>' . "\n";
    $showcase .= '[ux_image id="' . $attach_id . '" image_size="original" width="100"]';

    // Add to each product
    foreach ($ss['product_ids'] as $product_id) {
        $result = $conn->query("SELECT post_content, post_title FROM wp_posts WHERE ID=$product_id AND post_type='product' LIMIT 1");
        $row = $result->fetch_assoc();
        if (!$row) {
            echo "  Product $product_id NOT FOUND\n";
            continue;
        }

        if (strpos($row['post_content'], '<!-- Product Showcase -->') !== false) {
            echo "  → {$row['post_title']} ($product_id): showcase already exists, skipping\n";
            continue;
        }

        $new_content = $row['post_content'] . $showcase;
        $stmt = $conn->prepare("UPDATE wp_posts SET post_content=? WHERE ID=?");
        $stmt->bind_param('si', $new_content, $product_id);
        $stmt->execute();
        echo "  → {$row['post_title']} ($product_id): sell sheet added ✓\n";
        $total_products++;
    }

    echo "\n";
}

echo "=== Done — $total_products products updated with sell sheets ===\n";

$conn->close();
