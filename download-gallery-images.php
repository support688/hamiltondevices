<?php
/**
 * Download gallery images from Jupiter Research and attach as WooCommerce product galleries
 *
 * Gallery images are stored in _product_image_gallery as comma-separated attachment IDs.
 *
 * Run via: docker compose exec -T wordpress php < download-gallery-images.php
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Download Gallery Images ===\n\n";

$year_month = date('Y/m');
$upload_dir = "/var/www/html/wp-content/uploads/$year_month";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Helper: download image, create attachment, return attachment ID
function download_and_attach($conn, $url, $filename, $title, $product_id, $upload_dir, $year_month) {
    $local_path = "$upload_dir/$filename";
    $relative_path = "$year_month/$filename";

    // Skip if file already exists
    if (file_exists($local_path) && filesize($local_path) > 1000) {
        // Check if attachment already exists
        $check = $conn->prepare("SELECT p.ID FROM wp_posts p JOIN wp_postmeta pm ON p.ID=pm.post_id WHERE pm.meta_key='_wp_attached_file' AND pm.meta_value=? AND p.post_type='attachment' LIMIT 1");
        $check->bind_param('s', $relative_path);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        if ($row) {
            return $row['ID']; // Already exists
        }
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; HamiltonDevices/1.0)');
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($data) || strlen($data) < 1000) {
        return false;
    }

    file_put_contents($local_path, $data);
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
        ?, ?, 0, 'attachment', ?, 0
    )");
    $stmt->bind_param('ssssssiss', $now, $now_gmt, $title, $slug, $now, $now_gmt, $product_id, $guid, $mime_type);
    $stmt->execute();
    $attach_id = $conn->insert_id;

    if (!$attach_id) return false;

    $stmt2 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_wp_attached_file', ?)");
    $stmt2->bind_param('is', $attach_id, $relative_path);
    $stmt2->execute();

    $meta = serialize([
        'width' => $width, 'height' => $height, 'file' => $relative_path,
        'sizes' => [], 'image_meta' => ['aperture'=>'0','credit'=>'','camera'=>'','caption'=>'','created_timestamp'=>'0','copyright'=>'','focal_length'=>'0','iso'=>'0','shutter_speed'=>'0','title'=>'','orientation'=>'0','keywords'=>[]],
    ]);
    $stmt3 = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_wp_attachment_metadata', ?)");
    $stmt3->bind_param('is', $attach_id, $meta);
    $stmt3->execute();

    return $attach_id;
}

// Product galleries — product_id => array of [url, filename, title]
$galleries = [

    // ── THREDZ 0.5ml (243016) ──
    243016 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/10/1.0_Jupiter_IndividualProduct_Images_THREDZ_-01-scaled.jpg', 'ccell-thredz-gallery-01.jpg', 'CCELL THREDZ Detail 1'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/10/1.0_Jupiter_IndividualProduct_Images_THREDZ_-02-scaled.jpg', 'ccell-thredz-gallery-02.jpg', 'CCELL THREDZ Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/10/1.0_Jupiter_IndividualProduct_Images_THREDZ_-03-scaled.jpg', 'ccell-thredz-gallery-03.jpg', 'CCELL THREDZ Detail 3'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/10/1.0_Jupiter_IndividualProduct_Images_THREDZ_-04-scaled.jpg', 'ccell-thredz-gallery-04.jpg', 'CCELL THREDZ Detail 4'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/10/1.0_Jupiter_IndividualProduct_Images_THREDZ_-05-scaled.jpg', 'ccell-thredz-gallery-05.jpg', 'CCELL THREDZ Detail 5'],
    ],

    // ── THREDZ 1.0ml (243017) — share same gallery ──
    243017 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/10/1.0_Jupiter_IndividualProduct_Images_THREDZ_-02-scaled.jpg', 'ccell-thredz-gallery-02.jpg', 'CCELL THREDZ Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/10/1.0_Jupiter_IndividualProduct_Images_THREDZ_-03-scaled.jpg', 'ccell-thredz-gallery-03.jpg', 'CCELL THREDZ Detail 3'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/10/1.0_Jupiter_IndividualProduct_Images_THREDZ_-04-scaled.jpg', 'ccell-thredz-gallery-04.jpg', 'CCELL THREDZ Detail 4'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/10/1.0_Jupiter_IndividualProduct_Images_THREDZ_-05-scaled.jpg', 'ccell-thredz-gallery-05.jpg', 'CCELL THREDZ Detail 5'],
    ],

    // ── Palm SE (243033) ──
    243033 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/09/Jupiter_PS_PalmSE_01.jpg', 'ccell-palm-se-gallery-01.jpg', 'CCELL Palm SE Detail 1'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/09/Jupiter_PS_PalmSE_02.jpg', 'ccell-palm-se-gallery-02.jpg', 'CCELL Palm SE Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/09/Jupiter_PS_PalmSE_03.jpg', 'ccell-palm-se-gallery-03.jpg', 'CCELL Palm SE Detail 3'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/09/Jupiter_PS_PalmSE_04.jpg', 'ccell-palm-se-gallery-04.jpg', 'CCELL Palm SE Detail 4'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/09/Jupiter_PS_PalmSE_05.jpg', 'ccell-palm-se-gallery-05.jpg', 'CCELL Palm SE Detail 5'],
    ],

    // ── M4 Tiny (243034) ──
    243034 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/06/Jupiter_CCELL_PS_M4Tiny_01.jpg', 'ccell-m4-tiny-gallery-01.jpg', 'CCELL M4 Tiny Detail 1'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/06/Jupiter_CCELL_PS_M4Tiny_02.jpg', 'ccell-m4-tiny-gallery-02.jpg', 'CCELL M4 Tiny Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/06/Jupiter_CCELL_PS_M4Tiny_03.jpg', 'ccell-m4-tiny-gallery-03.jpg', 'CCELL M4 Tiny Detail 3'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/06/Jupiter_CCELL_PS_M4Tiny_05.jpg', 'ccell-m4-tiny-gallery-05.jpg', 'CCELL M4 Tiny Detail 5'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/06/Jupiter_CCELL_PS_M4Tiny_06.jpg', 'ccell-m4-tiny-gallery-06.jpg', 'CCELL M4 Tiny Detail 6'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/06/Jupiter_CCELL_PS_M4Tiny_07.jpg', 'ccell-m4-tiny-gallery-07.jpg', 'CCELL M4 Tiny Detail 7'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/06/Jupiter_CCELL_PS_M4Tiny_08.jpg', 'ccell-m4-tiny-gallery-08.jpg', 'CCELL M4 Tiny Detail 8'],
    ],

    // ── M4B Pro (243035) ──
    243035 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/04/Jupiter_CCELL_PS_M4BPro_01.jpg', 'ccell-m4b-pro-gallery-01.jpg', 'CCELL M4B Pro Detail 1'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/04/Jupiter_CCELL_PS_M4BPro_02.jpg', 'ccell-m4b-pro-gallery-02.jpg', 'CCELL M4B Pro Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/04/Jupiter_CCELL_PS_M4BPro_03.jpg', 'ccell-m4b-pro-gallery-03.jpg', 'CCELL M4B Pro Detail 3'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/04/Jupiter_CCELL_PS_M4BPro_04.jpg', 'ccell-m4b-pro-gallery-04.jpg', 'CCELL M4B Pro Detail 4'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/04/Jupiter_CCELL_PS_M4BPro_05.jpg', 'ccell-m4b-pro-gallery-05.jpg', 'CCELL M4B Pro Detail 5'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/04/Jupiter_CCELL_PS_M4BPro_06.jpg', 'ccell-m4b-pro-gallery-06.jpg', 'CCELL M4B Pro Detail 6'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/04/Jupiter_CCELL_PS_M4BPro_07.jpg', 'ccell-m4b-pro-gallery-07.jpg', 'CCELL M4B Pro Detail 7'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/04/Jupiter_CCELL_PS_M4BPro_08.jpg', 'ccell-m4b-pro-gallery-08.jpg', 'CCELL M4B Pro Detail 8'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/04/Jupiter_CCELL_PS_M4BPro_09.jpg', 'ccell-m4b-pro-gallery-09.jpg', 'CCELL M4B Pro Detail 9'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/04/Jupiter_CCELL_PS_M4BPro_10.jpg', 'ccell-m4b-pro-gallery-10.jpg', 'CCELL M4B Pro Detail 10'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/04/Jupiter_CCELL_PS_M4BPro_11.jpg', 'ccell-m4b-pro-gallery-11.jpg', 'CCELL M4B Pro Detail 11'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/04/Jupiter_CCELL_PS_M4BPro_13.jpg', 'ccell-m4b-pro-gallery-13.jpg', 'CCELL M4B Pro Detail 13'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/04/Jupiter_CCELL_PS_M4BPro_14.jpg', 'ccell-m4b-pro-gallery-14.jpg', 'CCELL M4B Pro Detail 14'],
    ],

    // ── Blade 1.0ml AIO (243037) ──
    243037 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_1mL_02.jpg', 'ccell-blade-1ml-gallery-02.jpg', 'CCELL Blade 1.0ml Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_1mL_03.jpg', 'ccell-blade-1ml-gallery-03.jpg', 'CCELL Blade 1.0ml Detail 3'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_1mL_04.jpg', 'ccell-blade-1ml-gallery-04.jpg', 'CCELL Blade 1.0ml Detail 4'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_1mL_05.jpg', 'ccell-blade-1ml-gallery-05.jpg', 'CCELL Blade 1.0ml Detail 5'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_1mL_10.jpg', 'ccell-blade-1ml-gallery-10.jpg', 'CCELL Blade 1.0ml Detail 10'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_1mL_11.jpg', 'ccell-blade-1ml-gallery-11.jpg', 'CCELL Blade 1.0ml Detail 11'],
    ],

    // ── Blade 2.0ml AIO (243038) ──
    243038 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_2mL_01.jpg', 'ccell-blade-2ml-gallery-01.jpg', 'CCELL Blade 2.0ml Detail 1'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_2mL_02.jpg', 'ccell-blade-2ml-gallery-02.jpg', 'CCELL Blade 2.0ml Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_2mL_03.jpg', 'ccell-blade-2ml-gallery-03.jpg', 'CCELL Blade 2.0ml Detail 3'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_2mL_04.jpg', 'ccell-blade-2ml-gallery-04.jpg', 'CCELL Blade 2.0ml Detail 4'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_2mL_05.jpg', 'ccell-blade-2ml-gallery-05.jpg', 'CCELL Blade 2.0ml Detail 5'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_2mL_06.jpg', 'ccell-blade-2ml-gallery-06.jpg', 'CCELL Blade 2.0ml Detail 6'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_2mL_07.jpg', 'ccell-blade-2ml-gallery-07.jpg', 'CCELL Blade 2.0ml Detail 7'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_2mL_08.jpg', 'ccell-blade-2ml-gallery-08.jpg', 'CCELL Blade 2.0ml Detail 8'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_2mL_09.jpg', 'ccell-blade-2ml-gallery-09.jpg', 'CCELL Blade 2.0ml Detail 9'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/01/Jupiter_CCELL_AIO_Blade_2mL_10.jpg', 'ccell-blade-2ml-gallery-10.jpg', 'CCELL Blade 2.0ml Detail 10'],
    ],

    // ── TurBoom (243039) ──
    243039 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/1.0_Jupiter_Products_Turboom-01.jpg', 'ccell-turboom-gallery-01.jpg', 'CCELL TurBoom Detail 1'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/1.0_Jupiter_Products_Turboom-02.jpg', 'ccell-turboom-gallery-02.jpg', 'CCELL TurBoom Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/1.0_Jupiter_Products_Turboom-03.jpg', 'ccell-turboom-gallery-03.jpg', 'CCELL TurBoom Detail 3'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/1.0_Jupiter_Products_Turboom-04.jpg', 'ccell-turboom-gallery-04.jpg', 'CCELL TurBoom Detail 4'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/1.0_Jupiter_Products_Turboom-05.jpg', 'ccell-turboom-gallery-05.jpg', 'CCELL TurBoom Detail 5'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/1.0_Jupiter_Products_Turboom-06.jpg', 'ccell-turboom-gallery-06.jpg', 'CCELL TurBoom Detail 6'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/1.0_Jupiter_Products_Turboom-07.jpg', 'ccell-turboom-gallery-07.jpg', 'CCELL TurBoom Detail 7'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/1.0_Jupiter_Products_Turboom-08.jpg', 'ccell-turboom-gallery-08.jpg', 'CCELL TurBoom Detail 8'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/1.0_Jupiter_Products_Turboom-10.jpg', 'ccell-turboom-gallery-10.jpg', 'CCELL TurBoom Detail 10'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/1.0_Jupiter_Products_Turboom-11.jpg', 'ccell-turboom-gallery-11.jpg', 'CCELL TurBoom Detail 11'],
    ],

    // ── Flexcell X (243040) — use 1-2mL variant images ──
    243040 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/05/Jupiter_CCELL_FlexcellX_1-2mL_01.jpg', 'ccell-flexcellx-gallery-01.jpg', 'CCELL Flexcell X Detail 1'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/05/Jupiter_CCELL_FlexcellX_1-2mL_02.jpg', 'ccell-flexcellx-gallery-02.jpg', 'CCELL Flexcell X Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/05/Jupiter_CCELL_FlexcellX_1-2mL_03.jpg', 'ccell-flexcellx-gallery-03.jpg', 'CCELL Flexcell X Detail 3'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/05/Jupiter_CCELL_FlexcellX_1-2mL_04.jpg', 'ccell-flexcellx-gallery-04.jpg', 'CCELL Flexcell X Detail 4'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/05/Jupiter_CCELL_FlexcellX_1-2mL_05.jpg', 'ccell-flexcellx-gallery-05.jpg', 'CCELL Flexcell X Detail 5'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/05/Jupiter_CCELL_FlexcellX_1-2mL_06.jpg', 'ccell-flexcellx-gallery-06.jpg', 'CCELL Flexcell X Detail 6'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/05/Jupiter_CCELL_FlexcellX_1-2mL_07.jpg', 'ccell-flexcellx-gallery-07.jpg', 'CCELL Flexcell X Detail 7'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/05/Jupiter_CCELL_FlexcellX_1-2mL_08.jpg', 'ccell-flexcellx-gallery-08.jpg', 'CCELL Flexcell X Detail 8'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/05/Jupiter_CCELL_FlexcellX_Charging.jpg', 'ccell-flexcellx-gallery-charging.jpg', 'CCELL Flexcell X Charging'],
    ],

    // ── Infinity (243041) ──
    243041 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/01_Jupiter_CCELL_AIO_Infinity_1mL.jpg', 'ccell-infinity-gallery-01.jpg', 'CCELL Infinity Detail 1'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/02_Jupiter_CCELL_AIO_Infinity_1mL_Size.jpg', 'ccell-infinity-gallery-02.jpg', 'CCELL Infinity Size Comparison'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/03_Jupiter_CCELL_Infinity_MicroUSB_Charge.jpg', 'ccell-infinity-gallery-03.jpg', 'CCELL Infinity Charging'],
    ],

    // ── Liquid X Glass (243042) ──
    243042 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/01_Jupiter_CCELL_AIO_LXG_1mL.jpg', 'ccell-lxg-gallery-01.jpg', 'CCELL Liquid X Glass 1.0ml'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/02_Jupiter_CCELL_AIO_LXG_1mL_Size.jpg', 'ccell-lxg-gallery-02.jpg', 'CCELL Liquid X Glass Size'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/03_Jupiter_CCELL_AIO_LXG_05mL.jpg', 'ccell-lxg-gallery-03.jpg', 'CCELL Liquid X Glass 0.5ml'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/04_Jupiter_CCELL_AIO_LXG_05mL_Size.jpg', 'ccell-lxg-gallery-04.jpg', 'CCELL Liquid X Glass 0.5ml Size'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/05_Jupiter_CCELL_LXG_MicroUSB_Charge.jpg', 'ccell-lxg-gallery-05.jpg', 'CCELL Liquid X Glass Charging'],
    ],

    // ── Airone (243043) — use 1mL variant gallery ──
    243043 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/Jupiter_AIO_CCELL_Airone_1mL_ProductCarousel_01.jpg', 'ccell-airone-gallery-01.jpg', 'CCELL Airone Detail 1'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/Jupiter_AIO_CCELL_Airone_1mL_ProductCarousel_02.jpg', 'ccell-airone-gallery-02.jpg', 'CCELL Airone Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/Jupiter_AIO_CCELL_Airone_1mL_ProductCarousel_03.jpg', 'ccell-airone-gallery-03.jpg', 'CCELL Airone Detail 3'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/Jupiter_AIO_CCELL_Airone_1mL_ProductCarousel_04.jpg', 'ccell-airone-gallery-04.jpg', 'CCELL Airone Detail 4'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/Jupiter_AIO_CCELL_Airone_1mL_ProductCarousel_05.jpg', 'ccell-airone-gallery-05.jpg', 'CCELL Airone Detail 5'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/Jupiter_AIO_CCELL_Airone_1mL_ProductCarousel_06.jpg', 'ccell-airone-gallery-06.jpg', 'CCELL Airone Detail 6'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/Jupiter_AIO_CCELL_Airone_1mL_ProductCarousel_07.jpg', 'ccell-airone-gallery-07.jpg', 'CCELL Airone Detail 7'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/Jupiter_AIO_CCELL_Airone_1mL_ProductCarousel_08.jpg', 'ccell-airone-gallery-08.jpg', 'CCELL Airone Detail 8'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/12/Jupiter_AIO_CCELL_Airone_1mL_ProductCarousel_10.jpg', 'ccell-airone-gallery-10.jpg', 'CCELL Airone Detail 10'],
    ],

    // ── Easy Pod (243044) ──
    243044 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/08/Jupiter_CCELL_Pods_EZ_EasyPod_01.jpg', 'ccell-easypod-gallery-01.jpg', 'CCELL Easy Pod Detail 1'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/08/Jupiter_CCELL_Pods_EZ_EasyPod_02.jpg', 'ccell-easypod-gallery-02.jpg', 'CCELL Easy Pod Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/08/Jupiter_CCELL_Pods_EZ_EasyPod_03.jpg', 'ccell-easypod-gallery-03.jpg', 'CCELL Easy Pod Detail 3'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/08/Jupiter_CCELL_Pods_EZ_EasyPod_04.jpg', 'ccell-easypod-gallery-04.jpg', 'CCELL Easy Pod Detail 4'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/08/Jupiter_CCELL_Pods_EZ_EasyPod_05.jpg', 'ccell-easypod-gallery-05.jpg', 'CCELL Easy Pod Detail 5'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/08/Jupiter_CCELL_Pods_EZ_EasyPod_06.jpg', 'ccell-easypod-gallery-06.jpg', 'CCELL Easy Pod Detail 6'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/08/Jupiter_CCELL_Pods_EZ_EasyPod_07.jpg', 'ccell-easypod-gallery-07.jpg', 'CCELL Easy Pod Detail 7'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/08/Jupiter_CCELL_Pods_EZ_EasyPod_08.jpg', 'ccell-easypod-gallery-08.jpg', 'CCELL Easy Pod Detail 8'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/08/Jupiter_CCELL_Pods_EZ_EasyPod_09.jpg', 'ccell-easypod-gallery-09.jpg', 'CCELL Easy Pod Detail 9'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2025/08/Jupiter_CCELL_Pods_EZ_EasyPod_10.jpg', 'ccell-easypod-gallery-10.jpg', 'CCELL Easy Pod Detail 10'],
    ],

    // ── Luster Pro (243045) ──
    243045 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/Jupiter_CCELL_Pod_LusterPro_02.jpg', 'ccell-lusterpro-gallery-02.jpg', 'CCELL Luster Pro Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/Jupiter_CCELL_Pods_LusterPro_Size_03.jpg', 'ccell-lusterpro-gallery-03.jpg', 'CCELL Luster Pro Size'],
    ],

    // ── Liquid Que (243046) ──
    243046 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/Jupiter_CCELL_Pod_Que_02.jpg', 'ccell-que-gallery-02.jpg', 'CCELL Liquid Que Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/Jupiter_CCELL_Pods_Que_Size_03.jpg', 'ccell-que-gallery-03.jpg', 'CCELL Liquid Que Size'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2024/12/Jupiter_CCELL_Que_MicroUSB_Charge.jpg', 'ccell-que-gallery-04.jpg', 'CCELL Liquid Que Charging'],
    ],

    // ── Kap (243047) ──
    243047 => [
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/02/Jupiter_CCELL_PS_Carousel_Kap_01.jpg', 'ccell-kap-gallery-01.jpg', 'CCELL Kap Detail 1'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/02/Jupiter_CCELL_PS_Carousel_Kap_02.jpg', 'ccell-kap-gallery-02.jpg', 'CCELL Kap Detail 2'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/02/Jupiter_CCELL_PS_Carousel_Kap_03.jpg', 'ccell-kap-gallery-03.jpg', 'CCELL Kap Detail 3'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/02/Jupiter_CCELL_PS_Carousel_Kap_04.jpg', 'ccell-kap-gallery-04.jpg', 'CCELL Kap Detail 4'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/02/Jupiter_CCELL_PS_Carousel_Kap_05.jpg', 'ccell-kap-gallery-05.jpg', 'CCELL Kap Detail 5'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/02/Jupiter_CCELL_PS_Carousel_Kap_06.jpg', 'ccell-kap-gallery-06.jpg', 'CCELL Kap Detail 6'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/02/Jupiter_CCELL_PS_Carousel_Kap_07.jpg', 'ccell-kap-gallery-07.jpg', 'CCELL Kap Detail 7'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/02/Jupiter_CCELL_PS_Carousel_Kap_08.jpg', 'ccell-kap-gallery-08.jpg', 'CCELL Kap Detail 8'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/02/Jupiter_CCELL_PS_Carousel_Kap_09.jpg', 'ccell-kap-gallery-09.jpg', 'CCELL Kap Detail 9'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/02/Jupiter_CCELL_PS_Carousel_Kap_10.jpg', 'ccell-kap-gallery-10.jpg', 'CCELL Kap Detail 10'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/02/Jupiter_CCELL_PS_Carousel_Kap_11.jpg', 'ccell-kap-gallery-11.jpg', 'CCELL Kap Detail 11'],
        ['https://www.jupiterresearch.com/wp-content/uploads/2026/02/Jupiter_CCELL_PS_Carousel_Kap_12.jpg', 'ccell-kap-gallery-12.jpg', 'CCELL Kap Detail 12'],
    ],
];

$total_downloaded = 0;
$total_failed = 0;

foreach ($galleries as $product_id => $images) {
    $check = $conn->query("SELECT post_title FROM wp_posts WHERE ID=$product_id AND post_type='product'");
    $prod = $check->fetch_assoc();
    if (!$prod) {
        echo "SKIP: Product ID $product_id not found\n";
        continue;
    }

    echo "\n── {$prod['post_title']} (ID: $product_id) ──\n";

    $gallery_ids = [];
    foreach ($images as $img) {
        echo "  Downloading: {$img[1]}... ";
        $attach_id = download_and_attach($conn, $img[0], $img[1], $img[2], $product_id, $upload_dir, $year_month);
        if ($attach_id) {
            echo "OK (ID: $attach_id)\n";
            $gallery_ids[] = $attach_id;
            $total_downloaded++;
        } else {
            echo "FAILED\n";
            $total_failed++;
        }
    }

    // Set _product_image_gallery meta
    if (!empty($gallery_ids)) {
        $gallery_str = implode(',', $gallery_ids);
        $existing = $conn->query("SELECT meta_id FROM wp_postmeta WHERE post_id=$product_id AND meta_key='_product_image_gallery' LIMIT 1");
        if ($existing->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE wp_postmeta SET meta_value=? WHERE post_id=? AND meta_key='_product_image_gallery'");
            $stmt->bind_param('si', $gallery_str, $product_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_product_image_gallery', ?)");
            $stmt->bind_param('is', $product_id, $gallery_str);
            $stmt->execute();
        }
        echo "  Gallery set: " . count($gallery_ids) . " images\n";
    }
}

echo "\n=== Gallery Download Complete ===\n";
echo "Total downloaded: $total_downloaded\n";
echo "Total failed: $total_failed\n";
echo "Done!\n";

$conn->close();
