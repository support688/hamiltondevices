<?php
/**
 * Add launch file sliders to all products
 *
 * Images already converted to JPGs in /var/www/html/wp-content/uploads/2026/03/
 * This script creates attachment records and appends [ux_slider] to each product.
 *
 * Run via: docker compose exec -T wordpress php < add-launch-sliders.php
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Add Launch File Sliders to All Products ===\n\n";

$year_month = '2026/03';
$upload_dir = "/var/www/html/wp-content/uploads/$year_month";

// ── Launch file mappings ──
$launch_files = [
    [
        'slug' => 'voca',
        'label' => 'Voca',
        'pages' => 9,
        'product_ids' => [190254, 190344, 215389, 215364],
    ],
    [
        'slug' => 'minitank',
        'label' => 'Mini Tank',
        'pages' => 10,
        'product_ids' => [243207, 243218, 243220],
    ],
    [
        'slug' => 'mixjoy',
        'label' => 'MixJoy',
        'pages' => 9,
        'product_ids' => [239197, 239213],
    ],
    [
        'slug' => 'flex',
        'label' => 'Flex Series',
        'pages' => 12,
        'product_ids' => [187689, 202258, 190306, 243040],
    ],
    [
        'slug' => 'easybar',
        'label' => 'Easy Bar',
        'pages' => 4,
        'product_ids' => [237749],
    ],
    [
        'slug' => 'gembar',
        'label' => 'Gem Bar',
        'pages' => 9,
        'product_ids' => [237765, 239919],
    ],
    [
        'slug' => 'gembox',
        'label' => 'Gem Box',
        'pages' => 9,
        'product_ids' => [241683],
    ],
    [
        'slug' => 'blade',
        'label' => 'Blade',
        'pages' => 9,
        'product_ids' => [243037, 243038],
    ],
    [
        'slug' => 'airone',
        'label' => 'Airone',
        'pages' => 10,
        'product_ids' => [243043],
    ],
    [
        'slug' => 'turboom',
        'label' => 'TurBoom',
        'pages' => 7,
        'product_ids' => [243039],
    ],
];

// ── Helper: create attachment record ──
function create_attachment($conn, $filename, $title, $upload_dir, $year_month) {
    $local_path = "$upload_dir/$filename";
    $relative_path = "$year_month/$filename";

    if (!file_exists($local_path)) {
        echo "    NOT FOUND: $local_path\n";
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
        'width' => $width,
        'height' => $height,
        'file' => $relative_path,
        'sizes' => [],
        'image_meta' => [
            'aperture' => '0', 'credit' => '', 'camera' => '', 'caption' => '',
            'created_timestamp' => '0', 'copyright' => '', 'focal_length' => '0',
            'iso' => '0', 'shutter_speed' => '0', 'title' => '', 'orientation' => '0',
            'keywords' => []
        ]
    ]);
    $stmt3 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_wp_attachment_metadata', ?)");
    $stmt3->bind_param('is', $attach_id, $meta);
    $stmt3->execute();

    return $attach_id;
}

// ── Process each launch file ──

$total_products = 0;
$total_attachments = 0;

foreach ($launch_files as $lf) {
    $slug = $lf['slug'];
    $label = $lf['label'];
    $pages = $lf['pages'];
    $product_ids = $lf['product_ids'];

    echo "══ $label ($pages pages → " . count($product_ids) . " products) ══\n";

    // Step 1: Create attachment records for each page
    $page_ids = [];
    for ($i = 1; $i <= $pages; $i++) {
        $num = sprintf('%02d', $i);
        $filename = "{$slug}-launch-page-{$num}.jpg";
        $title = "$label Launch File — Page $i";
        echo "  $filename... ";
        $id = create_attachment($conn, $filename, $title, $upload_dir, $year_month);
        if ($id) {
            echo "OK (ID: $id)\n";
            $page_ids[] = $id;
            $total_attachments++;
        } else {
            echo "FAILED\n";
        }
    }

    if (empty($page_ids)) {
        echo "  ⚠ No pages created, skipping products\n\n";
        continue;
    }

    // Step 2: Build slider shortcode
    $slider = "\n\n<!-- Product Showcase -->\n";
    $slider .= '<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>' . "\n";
    $slider .= '[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]' . "\n";
    foreach ($page_ids as $pid) {
        $slider .= '[ux_image id="' . $pid . '" image_size="original" width="100"]' . "\n";
    }
    $slider .= '[/ux_slider]';

    // Step 3: Append slider to each product's post_content
    foreach ($product_ids as $product_id) {
        // Get current content
        $result = $conn->query("SELECT post_content, post_title FROM wp_posts WHERE ID=$product_id AND post_type='product' LIMIT 1");
        $row = $result->fetch_assoc();
        if (!$row) {
            echo "  Product $product_id NOT FOUND\n";
            continue;
        }

        $current_content = $row['post_content'];
        $title = $row['post_title'];

        // Check if slider already added
        if (strpos($current_content, '<!-- Product Showcase -->') !== false) {
            echo "  → $title ($product_id): slider already exists, skipping\n";
            continue;
        }

        $new_content = $current_content . $slider;
        $stmt = $conn->prepare("UPDATE wp_posts SET post_content=? WHERE ID=?");
        $stmt->bind_param('si', $new_content, $product_id);
        $stmt->execute();
        echo "  → $title ($product_id): slider added ✓\n";
        $total_products++;
    }

    echo "\n";
}

echo "══════════════════════════════════════════\n";
echo "=== Done — $total_attachments page attachments, $total_products products updated ===\n";

$conn->close();
