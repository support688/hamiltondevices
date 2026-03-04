<?php
/**
 * Add TH2-SE and Listo launch file sliders to products
 * (converted from PPTX → PDF → JPG)
 *
 * Run via: docker compose exec -T wordpress php < add-pptx-launch-sliders.php
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Add PPTX Launch File Sliders ===\n\n";

$year_month = '2026/03';
$upload_dir = "/var/www/html/wp-content/uploads/$year_month";

// ── Helper: create attachment record ──
function create_attachment($conn, $filename, $title, $upload_dir, $year_month) {
    $local_path = "$upload_dir/$filename";
    $relative_path = "$year_month/$filename";

    if (!file_exists($local_path)) {
        echo "    NOT FOUND: $local_path\n";
        return false;
    }

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

$launch_files = [
    [
        'slug' => 'th2se',
        'label' => 'TH2-SE',
        'pages' => 8,
        'product_ids' => [
            // TH210-SE
            221042, 221044, 229958, 229965, 238452,
            // M6T10-SE
            221019, 221024, 221028, 218049,
            // M6T05-SE
            230111, 230105,
        ],
    ],
    [
        'slug' => 'listo',
        'label' => 'Listo',
        'pages' => 9,
        'product_ids' => [139550],
    ],
];

$total_products = 0;
$total_attachments = 0;

foreach ($launch_files as $lf) {
    $slug = $lf['slug'];
    $label = $lf['label'];
    $pages = $lf['pages'];
    $product_ids = $lf['product_ids'];

    echo "══ $label ($pages pages → " . count($product_ids) . " products) ══\n";

    // Create attachment records
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
        echo "  No pages created, skipping\n\n";
        continue;
    }

    // Build slider shortcode
    $slider = "\n\n<!-- Launch File -->\n";
    $slider .= '<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Launch File</h3>' . "\n";
    $slider .= '[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]' . "\n";
    foreach ($page_ids as $pid) {
        $slider .= '[ux_image id="' . $pid . '" image_size="original" width="100"]' . "\n";
    }
    $slider .= '[/ux_slider]';

    // Add to each product
    foreach ($product_ids as $product_id) {
        $result = $conn->query("SELECT post_content, post_title FROM wp_posts WHERE ID=$product_id AND post_type='product' LIMIT 1");
        $row = $result->fetch_assoc();
        if (!$row) {
            echo "  Product $product_id NOT FOUND\n";
            continue;
        }

        $current_content = $row['post_content'];
        $title = $row['post_title'];

        // Check if launch file already added
        if (strpos($current_content, '<!-- Launch File -->') !== false) {
            echo "  → $title ($product_id): launch file already exists, skipping\n";
            continue;
        }

        // For Listo which doesn't have Product Showcase yet, use Product Showcase marker
        if (strpos($current_content, '<!-- Product Showcase -->') === false) {
            // No existing showcase — use "Product Showcase" heading instead
            $slider_alt = "\n\n<!-- Product Showcase -->\n";
            $slider_alt .= '<h3 style="text-align:center;margin-top:40px;margin-bottom:20px;">Product Showcase</h3>' . "\n";
            $slider_alt .= '[ux_slider bg_color="rgb(30,30,30)" hide_nav="true" nav_style="simple" nav_color="light" bullets="true" bullet_style="dashes" auto_slide="false"]' . "\n";
            foreach ($page_ids as $pid) {
                $slider_alt .= '[ux_image id="' . $pid . '" image_size="original" width="100"]' . "\n";
            }
            $slider_alt .= '[/ux_slider]';
            $new_content = $current_content . $slider_alt;
        } else {
            // Has sell sheet showcase — add launch file below it
            $new_content = $current_content . $slider;
        }

        $stmt = $conn->prepare("UPDATE wp_posts SET post_content=? WHERE ID=?");
        $stmt->bind_param('si', $new_content, $product_id);
        $stmt->execute();
        echo "  → $title ($product_id): launch file slider added ✓\n";
        $total_products++;
    }

    echo "\n";
}

echo "=== Done — $total_attachments page attachments, $total_products products updated ===\n";

$conn->close();
