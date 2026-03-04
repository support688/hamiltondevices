<?php
/**
 * Fix Easy/SE tier product descriptions
 *
 * The Easy Cart and M6T05-S products use the SE Platform (NOT EVO).
 * This script corrects descriptions that incorrectly reference "EVO coil".
 *
 * Run via: docker compose exec -T wordpress php < fix-easy-product-descriptions.php
 */

$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Fix Easy/SE Product Descriptions ===\n\n";

// M6T05-S 0.5ml variants — these use SE Platform
$easy_s_products = [221071, 221070, 221066, 221062];

$easy_s_desc = 'The CCELL M6T05-S is a 0.5ml Engineering ThermoPlastic (ETP) cartridge featuring the original CCELL SE Atomizer Platform. The SE ceramic element delivers consistent, smooth vapor ideal for distillate formulations. As the most cost-effective CCELL cartridge, it is built for high-volume wholesale programs.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity ETP tank</li>
<li>CCELL SE ceramic heating element</li>
<li>510 thread connection</li>
<li>Optimized for distillate formulations</li>
<li>1.4&#8486; resistance</li>
<li>4 x &#248;2mm aperture inlets</li>
<li>Snap-fit or press-fit closure</li>
<li>Shatter-resistant ETP body</li>
<li>Viscosity range: 10,000 – 700,000 cP</li>
</ul>';

$easy_s_short = 'CCELL M6T05-S 0.5ml ETP cartridge with SE ceramic heating. Best value CCELL option for high-volume distillate programs. 1.4 ohm, 510 thread.';
$easy_s_meta = 'CCELL M6T05-S 0.5ml ETP cartridge with SE ceramic heating element. Optimized for distillate. Wholesale pricing from Hamilton Devices.';

$now = date('Y-m-d H:i:s');
$now_gmt = gmdate('Y-m-d H:i:s');

foreach ($easy_s_products as $pid) {
    $check = $conn->query("SELECT post_title FROM wp_posts WHERE ID=$pid AND post_type='product'");
    $row = $check->fetch_assoc();
    if (!$row) { echo "  SKIP: ID $pid not found\n"; continue; }

    $stmt = $conn->prepare("UPDATE wp_posts SET post_content=?, post_excerpt=?, post_modified=?, post_modified_gmt=? WHERE ID=?");
    $stmt->bind_param('ssssi', $easy_s_desc, $easy_s_short, $now, $now_gmt, $pid);
    $stmt->execute();

    $stmt2 = $conn->prepare("UPDATE wp_postmeta SET meta_value=? WHERE post_id=? AND meta_key='_yoast_wpseo_metadesc'");
    $stmt2->bind_param('si', $easy_s_meta, $pid);
    $stmt2->execute();

    echo "  Fixed: {$row['post_title']} (ID: $pid) — SE Platform\n";
}

// M6T05-SE (230111) — also SE Platform
$se_desc = 'The CCELL M6T05-SE (Special Edition) is a 0.5ml ETP cartridge featuring the CCELL SE ceramic heating element with enhanced design refinements. Combines proven SE platform performance with a premium aesthetic for brands that want reliable hardware with a polished look.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity ETP tank</li>
<li>CCELL SE ceramic heating element</li>
<li>Special Edition design refinements</li>
<li>510 thread connection</li>
<li>Optimized for distillate formulations</li>
<li>1.4&#8486; resistance</li>
<li>4 x &#248;2mm aperture inlets</li>
<li>Shatter-resistant ETP body</li>
<li>Viscosity range: 10,000 – 700,000 cP</li>
</ul>';

$se_short = 'CCELL M6T05-SE Special Edition 0.5ml ETP cartridge with SE ceramic heating. Proven performance with refined aesthetics.';
$se_meta = 'CCELL M6T05-SE Special Edition 0.5ml ETP cartridge with SE ceramic. Wholesale pricing from Hamilton Devices.';

$check = $conn->query("SELECT post_title FROM wp_posts WHERE ID=230111 AND post_type='product'");
$row = $check->fetch_assoc();
if ($row) {
    $stmt = $conn->prepare("UPDATE wp_posts SET post_content=?, post_excerpt=?, post_modified=?, post_modified_gmt=? WHERE ID=230111");
    $stmt->bind_param('ssss', $se_desc, $se_short, $now, $now_gmt);
    $stmt->execute();

    $stmt2 = $conn->prepare("UPDATE wp_postmeta SET meta_value=? WHERE post_id=230111 AND meta_key='_yoast_wpseo_metadesc'");
    $stmt2->bind_param('s', $se_meta);
    $stmt2->execute();

    echo "  Fixed: {$row['post_title']} (ID: 230111) — SE Platform\n";
}

// Also fix the Easy products created by create-easy-products.php (if they exist)
// TH205-Easy, TH210-Easy, M6T10-Easy — these should reference SE Platform
$easy_created_slugs = [
    'ccell-th205-easy-0-5ml-glass-cartridge-snap-fit',
    'ccell-th210-easy-1-0ml-glass-cartridge-snap-fit',
    'ccell-m6t10-easy-1-0ml-poly-cartridge-snap-fit',
];

// TH205-Easy 0.5ml Glass
$th205_desc = 'The CCELL TH205-Easy is a 0.5ml borosilicate glass cartridge from the Essential Series featuring the SE Atomizer Platform. The glass tank provides excellent oil visibility, while the snap-fit mouthpiece enables quick, tool-free assembly. Designed for value without compromising performance — ideal for distillate programs.

<strong>Key Features:</strong>
<ul>
<li>0.5ml capacity borosilicate glass tank</li>
<li>CCELL SE ceramic heating element</li>
<li>Stainless steel airway</li>
<li>Snap-fit closure</li>
<li>510 thread connection</li>
<li>1.4&#8486; resistance</li>
<li>Optimized for distillate formulations</li>
<li>Leak-free, clog-free design</li>
<li>Viscosity range: 10,000 – 700,000 cP</li>
<li>Dimensions: 51.8H x 10.5W x 10.5D mm</li>
</ul>';
$th205_short = 'CCELL TH205-Easy 0.5ml glass cartridge with SE ceramic heating. Best value glass cartridge for distillate programs.';
$th205_meta = 'CCELL TH205-Easy 0.5ml glass cartridge with SE ceramic. Snap-fit closure, 510 thread. Wholesale from Hamilton Devices.';

// TH210-Easy 1.0ml Glass
$th210_desc = 'The CCELL TH210-Easy is a 1.0ml borosilicate glass cartridge from the Essential Series featuring the SE Atomizer Platform. The full-gram glass tank is ideal for brands offering larger products with premium oil visibility. Snap-fit mouthpiece for quick assembly.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity borosilicate glass tank</li>
<li>CCELL SE ceramic heating element</li>
<li>Stainless steel airway</li>
<li>Snap-fit closure</li>
<li>510 thread connection</li>
<li>1.4&#8486; resistance</li>
<li>Optimized for distillate formulations</li>
<li>Leak-free, clog-free design</li>
<li>Viscosity range: 10,000 – 700,000 cP</li>
<li>Dimensions: 62.1H x 10.5W x 10.5D mm</li>
</ul>';
$th210_short = 'CCELL TH210-Easy 1.0ml glass cartridge with SE ceramic heating. Best value full-gram glass cartridge for distillate.';
$th210_meta = 'CCELL TH210-Easy 1.0ml glass cartridge with SE ceramic. Snap-fit, 510 thread. Wholesale from Hamilton Devices.';

// M6T10-Easy 1.0ml Poly
$m6t10_desc = 'The CCELL M6T10-Easy is a 1.0ml ETP (Engineering ThermoPlastic) cartridge from the Essential Series featuring the SE Atomizer Platform. The durable BPA-free ETP tank is ideal for high-volume programs prioritizing durability and cost-effectiveness.

<strong>Key Features:</strong>
<ul>
<li>1.0ml capacity BPA-free ETP tank</li>
<li>CCELL SE ceramic heating element</li>
<li>Stainless steel airway</li>
<li>Snap-fit closure</li>
<li>510 thread connection</li>
<li>1.4&#8486; resistance</li>
<li>Optimized for distillate formulations</li>
<li>Shatter-resistant body</li>
<li>Viscosity range: 10,000 – 700,000 cP</li>
</ul>';
$m6t10_short = 'CCELL M6T10-Easy 1.0ml ETP cartridge with SE ceramic heating. Best value full-gram ETP cartridge.';
$m6t10_meta = 'CCELL M6T10-Easy 1.0ml ETP cartridge with SE ceramic. Wholesale from Hamilton Devices.';

$updates = [
    ['slug' => 'ccell-th205-easy-0-5ml-glass-cartridge-snap-fit', 'desc' => $th205_desc, 'short' => $th205_short, 'meta' => $th205_meta],
    ['slug' => 'ccell-th210-easy-1-0ml-glass-cartridge-snap-fit', 'desc' => $th210_desc, 'short' => $th210_short, 'meta' => $th210_meta],
    ['slug' => 'ccell-m6t10-easy-1-0ml-poly-cartridge-snap-fit', 'desc' => $m6t10_desc, 'short' => $m6t10_short, 'meta' => $m6t10_meta],
];

foreach ($updates as $u) {
    $stmt = $conn->prepare("SELECT ID, post_title FROM wp_posts WHERE post_name=? AND post_type='product'");
    $stmt->bind_param('s', $u['slug']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) { echo "  SKIP: slug '{$u['slug']}' not found\n"; continue; }

    $pid = $row['ID'];
    $stmt2 = $conn->prepare("UPDATE wp_posts SET post_content=?, post_excerpt=?, post_modified=?, post_modified_gmt=? WHERE ID=?");
    $stmt2->bind_param('ssssi', $u['desc'], $u['short'], $now, $now_gmt, $pid);
    $stmt2->execute();

    $stmt3 = $conn->prepare("UPDATE wp_postmeta SET meta_value=? WHERE post_id=? AND meta_key='_yoast_wpseo_metadesc'");
    $stmt3->bind_param('si', $u['meta'], $pid);
    $stmt3->execute();

    echo "  Fixed: {$row['post_title']} (ID: $pid) — SE Platform\n";
}

// Also fix the EVOMAX product descriptions to use correct resistance (1.4Ω not 1.2Ω)
echo "\n── Fixing EVOMAX resistance in descriptions ──\n";

$evomax_products = [221083, 221082, 221078, 221074, 221032, 220993, 220987];
foreach ($evomax_products as $pid) {
    $r = $conn->query("SELECT post_content FROM wp_posts WHERE ID=$pid");
    $row = $r->fetch_assoc();
    if ($row && strpos($row['post_content'], '1.2') !== false) {
        $fixed = str_replace('~1.2', '~1.4', $row['post_content']);
        $fixed = str_replace('1.2Ω', '1.4Ω', $fixed);
        $stmt = $conn->prepare("UPDATE wp_posts SET post_content=? WHERE ID=?");
        $stmt->bind_param('si', $fixed, $pid);
        $stmt->execute();
        echo "  Fixed resistance in ID $pid\n";
    }
}

// Fix Kera description — confirm it uses EVOMAX with 1.7Ω resistance for ceramic
$kera_id = 171374;
$kera_desc = 'The CCELL Kera is a full-ceramic cartridge featuring the EVOMAX Atomizer Platform. The entire oil path — body, mouthpiece, and heating element — is ceramic construction with a ceramic airway, eliminating all metal contact with oil for the purest possible flavor profile.

Available in 0.5ml, 1.0ml, and 2.0ml capacities with snap-fit closure. The 1.7 ohm resistance is optimized for the full-ceramic construction.

<strong>Key Features:</strong>
<ul>
<li>Full ceramic construction — zero metal oil contact</li>
<li>EVOMAX oversized ceramic heating element</li>
<li>Ceramic airway</li>
<li>1.7&#8486; resistance</li>
<li>510 thread connection</li>
<li>Snap-fit closure</li>
<li>4 x &#248;2mm aperture inlets</li>
<li>Available in 0.5ml, 1.0ml, and 2.0ml</li>
<li>Handles all oil types: distillate, live resin, live rosin, liquid diamonds</li>
<li>Viscosity range: 10,000 – 2,000,000 cP</li>
</ul>';

$kera_short = 'CCELL Kera full-ceramic cartridge with EVOMAX heating. Ceramic body and airway — zero metal oil contact. 1.7 ohm, snap-fit.';
$kera_meta = 'CCELL Kera full-ceramic cartridge with EVOMAX heating. Zero metal oil contact, ceramic airway. Wholesale from Hamilton Devices.';

$stmt = $conn->prepare("UPDATE wp_posts SET post_content=?, post_excerpt=?, post_modified=?, post_modified_gmt=? WHERE ID=?");
$stmt->bind_param('ssssi', $kera_desc, $kera_short, $now, $now_gmt, $kera_id);
$stmt->execute();

$stmt2 = $conn->prepare("UPDATE wp_postmeta SET meta_value=? WHERE post_id=? AND meta_key='_yoast_wpseo_metadesc'");
$stmt2->bind_param('si', $kera_meta, $kera_id);
$stmt2->execute();

echo "  Fixed: Kera (ID: $kera_id) — EVOMAX 1.7 ohm, ceramic airway\n";

echo "\n=== Description Fixes Complete ===\n";
echo "Done!\n";

$conn->close();
