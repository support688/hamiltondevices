<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// Hide excess menu items by setting them to draft status
// Keep only the key items per category

// CCELL Batteries - keep: Palm Pro, Palm, Silo, M3, M3 Plus, Rizo
// Hide: 510 Go Stik (195133), M3B (75650), 510 Battery Sandwave (193412)
$hide_batteries = [195133, 75650, 193412];

// CCELL Cartridges - keep: Klean White, Kera, 0.5ml Glass, 1.0ml Glass, 0.5ml Poly, 1.0ml Poly
// Hide: Klean SS (197143), Klean Black (197144), TH205-Y (107425), TH210-Y (107426), TH205-RYS (75651),
//        ZICO 0.5 (159779), ZICO 1.0 (159780), TH2-EVO (180381), TH2Y EVO (180382), M6T-EVO 0.5 (180383), M6T-EVO 1.0 (180384)
$hide_cartridges = [197143, 197144, 107425, 107426, 75651, 159779, 159780, 180381, 180382, 180383, 180384];

// CCELL Pod Systems - keep all 7 (already compact)
$hide_pods = [];

// CCELL Disposables - keep: GemBox, Gem Bar 2.0, Gem Bar 1.0, Voca Pro, Flex Pro, Listo, Ridge, Skye II
// Hide: MixJoy 2.0 (241707), MixJoy 1.0 (241708), Voca 1.0 (197139), Flex 1.0 (197141), Blanc (193411),
//        Eazie (179878), Sima (169682), OWA (174921), SLYM (143241), Memento (143242), Pike (61284),
//        TH001 (114827), 0.3ml cat (35620), 0.5ml cat (35619), DS1903-M (123942), DS1903-U (123943)
$hide_disposables = [241707, 241708, 197139, 197141, 193411, 179878, 169682, 174921, 143241, 143242, 61284, 114827, 35620, 35619, 123942, 123943];

// Hamilton Devices - keep: DRACO, Jetstream Mini, Tombstone V2, Cloak V2, Jetstream, Butterfly
// Hide: Daypipe Mini (207539), Cube (75647), Gold Bar (75649), Cloak orig (75960), Gamer (148737),
//        Starship (155266), Nomad (155265), Tombstone orig (75648), THE SHIV (112069), PB1 (105861),
//        KR1 (89185), PS1 (89186), DAYPIPE (112070)
$hide_hamilton = [207539, 75647, 75649, 75960, 148737, 155266, 155265, 75648, 112069, 105861, 89185, 89186, 112070];

// Auxo - keep all 4
$hide_auxo = [];

// Vape Accessories - keep: Jetstream Concentrate Kit, KR1 O-rings, PS1 O-rings, Gift Card
// Hide: PS1 PCTG (168276), KR1 PCTG (168277), KR1 Glass (106064), PS1 Glass (106065),
//        Wax Coil (106066), Mini Cartomizer (138478), Nomad Wax Coil (159659)
$hide_accessories = [168276, 168277, 106064, 106065, 106066, 138478, 159659];

$all_hide = array_merge($hide_batteries, $hide_cartridges, $hide_pods, $hide_disposables, $hide_hamilton, $hide_auxo, $hide_accessories);

$count = 0;
foreach ($all_hide as $id) {
    $conn->query("UPDATE wp_posts SET post_status='draft' WHERE ID=$id AND post_type='nav_menu_item'");
    $count += $conn->affected_rows;
}
echo "Hidden $count menu items\n";

// Also add max-height CSS to the mega menu panel as a safety net
$result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='theme_mods_flatsome-child'");
$row = $result->fetch_assoc();
$mods = unserialize($row['option_value']);

$scroll_css = '
/* Mega menu max height */
#mega-menu-wrap-primary #mega-menu-primary > li.mega-menu-megamenu > ul.mega-sub-menu {
    max-height: calc(100vh - 100px) !important;
    overflow-y: auto !important;
}
';

// Append scroll CSS (check if not already there)
if (strpos($mods['html_custom_css'], 'Mega menu max height') === false) {
    $mods['html_custom_css'] .= $scroll_css;
    $serialized = serialize($mods);
    $stmt = $conn->prepare("UPDATE wp_options SET option_value=? WHERE option_name='theme_mods_flatsome-child'");
    $stmt->bind_param('s', $serialized);
    $stmt->execute();
    echo "Added scroll CSS\n";
}

echo "Done!\n";
$conn->close();
