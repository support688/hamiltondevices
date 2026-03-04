<?php
/**
 * Create attachment records for Bravo images and assign to product 243234
 *
 * Images already copied to /var/www/html/wp-content/uploads/2026/03/
 * This script creates the wp_posts attachment rows + meta, then sets
 * hero image (black-01) and gallery for the Bravo product.
 *
 * Run via: docker compose exec -T wordpress php < upload-bravo-images.php
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Upload Bravo Images ===\n\n";

$product_id = 243234;
$year_month = '2026/03';
$upload_dir = "/var/www/html/wp-content/uploads/$year_month";

function create_attachment($conn, $filename, $title, $product_id, $upload_dir, $year_month) {
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
    $mime_type = $img_info['mime'] ?? 'image/png';

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
        ?, ?, 0, 'attachment', ?, 0
    )");
    $stmt->bind_param('ssssssiss', $now, $now_gmt, $title, $slug, $now, $now_gmt, $product_id, $guid, $mime_type);
    $stmt->execute();
    $attach_id = $conn->insert_id;
    if (!$attach_id) return false;

    $stmt2 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_wp_attached_file', ?)");
    $stmt2->bind_param('is', $attach_id, $relative_path);
    $stmt2->execute();

    $meta = serialize(['width'=>$width,'height'=>$height,'file'=>$relative_path,'sizes'=>[],'image_meta'=>['aperture'=>'0','credit'=>'','camera'=>'','caption'=>'','created_timestamp'=>'0','copyright'=>'','focal_length'=>'0','iso'=>'0','shutter_speed'=>'0','title'=>'','orientation'=>'0','keywords'=>[]]]);
    $stmt3 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_wp_attachment_metadata', ?)");
    $stmt3->bind_param('is', $attach_id, $meta);
    $stmt3->execute();

    return $attach_id;
}

// ── Create attachments for all Bravo images ──

$all_ids = [];

// Black Bravo (1-15) — hero will be black-01
echo "── Black Bravo images ──\n";
for ($i = 1; $i <= 15; $i++) {
    $num = sprintf('%02d', $i);
    $filename = "ccell-bravo-black-$num.png";
    $title = "CCELL Bravo Black " . $i;
    echo "  $filename... ";
    $id = create_attachment($conn, $filename, $title, $product_id, $upload_dir, $year_month);
    if ($id) {
        echo "OK (ID: $id)\n";
        $all_ids["black-$num"] = $id;
    } else {
        echo "FAILED\n";
    }
}

// White Bravo (1-14)
echo "\n── White Bravo images ──\n";
for ($i = 1; $i <= 14; $i++) {
    $num = sprintf('%02d', $i);
    $filename = "ccell-bravo-white-$num.png";
    $title = "CCELL Bravo White " . $i;
    echo "  $filename... ";
    $id = create_attachment($conn, $filename, $title, $product_id, $upload_dir, $year_month);
    if ($id) {
        echo "OK (ID: $id)\n";
        $all_ids["white-$num"] = $id;
    } else {
        echo "FAILED\n";
    }
}

// ── Set hero image (black-01) ──

echo "\n── Setting hero + gallery ──\n";

$hero_id = $all_ids['black-01'] ?? null;
if ($hero_id) {
    $existing = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=$product_id AND meta_key='_thumbnail_id' LIMIT 1");
    if ($existing->num_rows > 0) {
        $conn->query("UPDATE wp_postmeta SET meta_value='$hero_id' WHERE post_id=$product_id AND meta_key='_thumbnail_id'");
    } else {
        $conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($product_id, '_thumbnail_id', '$hero_id')");
    }
    echo "  Hero image set: ID $hero_id (black-01)\n";
}

// ── Set gallery (all remaining images) ──

$gallery_ids = [];
foreach ($all_ids as $key => $id) {
    if ($key !== 'black-01') {
        $gallery_ids[] = $id;
    }
}

if (!empty($gallery_ids)) {
    $gallery_str = implode(',', $gallery_ids);
    $existing = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=$product_id AND meta_key='_product_image_gallery' LIMIT 1");
    if ($existing->num_rows > 0) {
        $conn->query("UPDATE wp_postmeta SET meta_value='$gallery_str' WHERE post_id=$product_id AND meta_key='_product_image_gallery'");
    } else {
        $conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($product_id, '_product_image_gallery', '$gallery_str')");
    }
    echo "  Gallery set: " . count($gallery_ids) . " images\n";
}

echo "\n=== Done — " . count($all_ids) . " images attached to Bravo (ID: $product_id) ===\n";

$conn->close();
