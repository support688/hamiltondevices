<?php
/**
 * Download product images from Jupiter Research and attach to WooCommerce products
 *
 * Downloads the main hero image for each product, saves to wp-content/uploads,
 * creates WordPress attachment entries, and sets _thumbnail_id.
 *
 * Run via: docker compose exec -T wordpress php < download-product-images.php
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Download & Attach Product Images ===\n\n";

// Upload directory
$year_month = date('Y/m');
$upload_dir = "/var/www/html/wp-content/uploads/$year_month";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
    echo "Created upload directory: $upload_dir\n";
}

// Product ID => [image_url, local_filename, title]
$images = [
    243016 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2024/09/1.0_Jupiter_Products_Thredz-scaled.jpg',
        'file'  => 'ccell-thredz-cartridge.jpg',
        'title' => 'CCELL THREDZ Stackable Cartridge',
    ],
    243017 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2024/10/1.0_Jupiter_IndividualProduct_Images_THREDZ_-01-scaled.jpg',
        'file'  => 'ccell-thredz-cartridge-detail.jpg',
        'title' => 'CCELL THREDZ 1.0ml Stackable Cartridge',
    ],
    243033 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2025/09/1.0_Jupiter_Products_PalmSE.jpg',
        'file'  => 'ccell-palm-se-battery.jpg',
        'title' => 'CCELL Palm SE 510 Battery',
    ],
    243034 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2025/06/1.0_Jupiter_Products_M4Tiny.jpg',
        'file'  => 'ccell-m4-tiny-battery.jpg',
        'title' => 'CCELL M4 Tiny 510 Battery',
    ],
    243035 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2025/04/1.0_Jupiter_Products_M4BPro.jpg',
        'file'  => 'ccell-m4b-pro-battery.jpg',
        'title' => 'CCELL M4B Pro 510 Battery',
    ],
    243037 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_1mL_01.jpg',
        'file'  => 'ccell-blade-1ml-aio.jpg',
        'title' => 'CCELL Blade 1.0ml AIO Disposable',
    ],
    243038 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2026/01/1.0_Jupiter_Products_Blade_2mL.jpg',
        'file'  => 'ccell-blade-2ml-aio.jpg',
        'title' => 'CCELL Blade 2.0ml AIO Disposable',
    ],
    243039 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2025/12/1.0_Jupiter_Products_Turboom.jpg',
        'file'  => 'ccell-turboom-aio.jpg',
        'title' => 'CCELL TurBoom 2.0ml Dual-Core AIO',
    ],
    243040 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2025/05/1.0_Jupiter_Products_FlexcellX.jpg',
        'file'  => 'ccell-flexcell-x-aio.jpg',
        'title' => 'CCELL Flexcell X AIO Disposable',
    ],
    243041 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2024/09/1.0_Jupiter_Products_Infinity-scaled.jpg',
        'file'  => 'ccell-infinity-aio.jpg',
        'title' => 'CCELL Infinity 1.0ml AIO Disposable',
    ],
    243042 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2024/09/1.0_Jupiter_Products_LXG-scaled.jpg',
        'file'  => 'ccell-liquid-x-glass-aio.jpg',
        'title' => 'CCELL Liquid X Glass AIO',
    ],
    243043 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2025/12/1.0_Jupiter_Airone_2mL.jpg',
        'file'  => 'ccell-airone-aio.jpg',
        'title' => 'CCELL Airone AIO Disposable',
    ],
    243044 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2025/08/1.0_Jupiter_Products_EasyPod.jpg',
        'file'  => 'ccell-easy-pod-system.jpg',
        'title' => 'CCELL Easy Pod System',
    ],
    243045 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2024/09/1.0_Jupiter_Products_Luster-Pro-scaled.jpg',
        'file'  => 'ccell-luster-pro-pod.jpg',
        'title' => 'CCELL Luster Pro Pod System',
    ],
    243046 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2024/09/1.0_Jupiter_Products_qUE-scaled.jpg',
        'file'  => 'ccell-liquid-que-pod.jpg',
        'title' => 'CCELL Liquid Que Pod System',
    ],
    243047 => [
        'url'   => 'https://www.jupiterresearch.com/wp-content/uploads/2026/02/Jupiter_CCELL_PS_Kap_01.jpg',
        'file'  => 'ccell-kap-510-power-supply.jpg',
        'title' => 'CCELL Kap 510 Power Supply',
    ],
];

$now = date('Y-m-d H:i:s');
$now_gmt = gmdate('Y-m-d H:i:s');
$success = 0;
$failed = 0;

foreach ($images as $product_id => $img) {
    // Check product exists
    $check = $conn->query("SELECT post_title FROM wp_posts WHERE ID=$product_id AND post_type='product'");
    $prod = $check->fetch_assoc();
    if (!$prod) {
        echo "SKIP: Product ID $product_id not found\n";
        $failed++;
        continue;
    }

    // Check if already has thumbnail
    $thumb_check = $conn->query("SELECT meta_value FROM wp_postmeta WHERE post_id=$product_id AND meta_key='_thumbnail_id' LIMIT 1");
    $thumb_row = $thumb_check->fetch_assoc();
    if ($thumb_row && !empty($thumb_row['meta_value']) && $thumb_row['meta_value'] !== '0') {
        echo "SKIP: {$prod['post_title']} already has thumbnail (attachment {$thumb_row['meta_value']})\n";
        continue;
    }

    $local_path = "$upload_dir/{$img['file']}";
    $relative_path = "$year_month/{$img['file']}";

    echo "Downloading: {$img['title']}... ";

    // Download with curl for better error handling
    $ch = curl_init($img['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; HamiltonDevices/1.0)');
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($http_code !== 200 || empty($data) || strlen($data) < 1000) {
        echo "FAILED (HTTP $http_code, " . strlen($data) . " bytes)\n";
        $failed++;
        continue;
    }

    // Save file
    file_put_contents($local_path, $data);
    $file_size = strlen($data);
    echo "OK (" . round($file_size / 1024) . " KB)\n";

    // Get image dimensions
    $img_info = getimagesize($local_path);
    $width = $img_info[0] ?? 0;
    $height = $img_info[1] ?? 0;
    $mime_type = $img_info['mime'] ?? 'image/jpeg';

    // Create attachment post
    $slug = pathinfo($img['file'], PATHINFO_FILENAME);
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
    $guid = "http://localhost:8080/wp-content/uploads/$relative_path";
    $stmt->bind_param('ssssssiss',
        $now, $now_gmt, $img['title'], $slug,
        $now, $now_gmt,
        $product_id, $guid, $mime_type
    );
    $stmt->execute();
    $attach_id = $conn->insert_id;

    if (!$attach_id) {
        echo "  ERROR: Failed to create attachment post\n";
        $failed++;
        continue;
    }

    // Attachment meta: _wp_attached_file
    $stmt2 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_wp_attached_file', ?)");
    $stmt2->bind_param('is', $attach_id, $relative_path);
    $stmt2->execute();

    // Attachment meta: _wp_attachment_metadata
    $meta = serialize([
        'width'  => $width,
        'height' => $height,
        'file'   => $relative_path,
        'sizes'  => [],
        'image_meta' => [
            'aperture' => '0', 'credit' => '', 'camera' => '',
            'caption' => '', 'created_timestamp' => '0',
            'copyright' => '', 'focal_length' => '0',
            'iso' => '0', 'shutter_speed' => '0',
            'title' => '', 'orientation' => '0',
            'keywords' => [],
        ],
    ]);
    $stmt3 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_wp_attachment_metadata', ?)");
    $stmt3->bind_param('is', $attach_id, $meta);
    $stmt3->execute();

    // Set as product thumbnail
    // First check if _thumbnail_id meta exists
    $existing = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=$product_id AND meta_key='_thumbnail_id' LIMIT 1");
    if ($existing->num_rows > 0) {
        $stmt4 = $conn->prepare("UPDATE wp_postmeta SET meta_value=? WHERE post_id=? AND meta_key='_thumbnail_id'");
        $stmt4->bind_param('si', $attach_id, $product_id);
        $stmt4->execute();
    } else {
        $stmt4 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_thumbnail_id', ?)");
        $aid_str = (string)$attach_id;
        $stmt4->bind_param('is', $product_id, $aid_str);
        $stmt4->execute();
    }

    echo "  Attached: ID $attach_id ({$width}x{$height}) → Product $product_id ({$prod['post_title']})\n";
    $success++;
}

echo "\n=== Image Download Complete ===\n";
echo "Success: $success\n";
echo "Failed: $failed\n";
echo "Done!\n";

$conn->close();
