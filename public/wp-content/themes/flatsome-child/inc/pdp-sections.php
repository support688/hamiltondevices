<?php
/**
 * PDP Informational Sections — Data-driven rendering engine
 *
 * Each CCELL technology tier has its own content configuration.
 * The renderer outputs 5 section blocks (Technology Overview, Specs,
 * Oil Compatibility, Materials & Compliance, Compare CTA) using
 * the tier's data array.
 *
 * Loaded via require_once from functions.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// Tier Data — keyed by product_cat slug
// =============================================================================
$pdp_tier_data = [

    // ── EVOMAX (Glass & ETP) ────────────────────────────────────────────────
    'ccell-evo-max' => [
        'tech_badge'       => 'EVOMAX Technology',
        'tech_name'        => 'CCELL EVOMAX Heating Technology',
        'tech_tagline'     => 'Oversized Ceramic. Thicker Walls. Dense, Potent Clouds.',
        'tech_description' => [
            'The EVOMAX platform features an oversized ceramic heating element with thicker walls and consistent, even pore distribution — engineered for superior heat distribution, eliminating burnt taste while producing rich, flavorful clouds.',
            'Built for high-viscosity extracts, the EVOMAX handles live rosins and liquid diamonds with ease. Its expanded viscosity range reaches up to 2,000,000 cP standard, or up to 5,000,000 cP with the enlarged airway configuration.',
        ],
        'tech_image'       => 'ccell-atomizer-evomax.png',
        'tech_image_alt'   => 'CCELL EVOMAX Atomizer',
        'key_stats' => [
            ['value' => '10K–2M cP',  'label' => 'Viscosity Range'],
            ['value' => '4 Oil Types', 'label' => 'Supported'],
            ['value' => '1st Puff',    'label' => 'Activation'],
        ],
        'specs' => [
            'Capacity'         => '0.5 ml / 1.0 ml / 1.2 ml (varies by model)',
            'Body Material'    => 'Borosilicate glass or Engineering ThermoPlastic (ETP)',
            'Coil Type'        => 'EVOMAX oversized ceramic',
            'Resistance'       => '~1.4 &#8486;',
            'Thread'           => '510',
            'Mouthpiece Type'  => 'Snap-fit or screw-on (varies by model)',
            'Aperture Inlets'  => '4 &times; &#8960;2 mm',
            'Airway'           => 'Medical-grade stainless steel',
            'Viscosity Range'  => '10,000 – 2,000,000 cP (standard); 700,000 – 5,000,000 cP (enlarged airway)',
            'Oil Compatibility'=> 'Distillate, Live Resin, Live Rosin, Liquid Diamonds',
        ],
        'oil_compat' => [
            ['name' => 'Distillate',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Resin',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Rosin',      'icon' => '&#x1F7E2;'],
            ['name' => 'Liquid Diamonds', 'icon' => '&#x1F7E2;'],
        ],
        'oil_note' => 'Recommended for live rosins and liquid diamonds. The EVOMAX oversized ceramic element with thicker walls and consistent, even pore distribution wicks oils across the full viscosity spectrum — from 10,000 cP distillates to 2,000,000 cP live rosins (up to 5,000,000 cP with enlarged airway) — without dry hits, clogging, or inconsistent vapor.',
        'materials' => [
            ['icon' => '&#x1F52C;', 'title' => 'Borosilicate Glass or BPA-Free ETP', 'desc' => 'Available in lab-grade borosilicate glass for full oil visibility, or BPA-free Engineering ThermoPlastic (ETP) for durability and design flexibility. No material interaction with oils at any viscosity.'],
            ['icon' => '&#x1F6E1;', 'title' => 'Medical-Grade Stainless Steel Airway', 'desc' => 'The airway uses medical-grade stainless steel for corrosion resistance and biocompatibility across all formulation types including live rosins and liquid diamonds.'],
            ['icon' => '&#x2705;',  'title' => 'Testing &amp; Compliance',       'desc' => 'Heavy metal tested with FDA-compliant wetted materials. CCELL maintains ISO-certified manufacturing with batch-level traceability.'],
        ],
        'cta_text' => 'Not sure which CCELL technology is right for your brand?',
        'cta_link' => '/ccell-heating-technology/',
        'cta_label' => 'Compare All Technologies',
    ],

    // ── Easy Cart (SE Platform) ─────────────────────────────────────────────
    'ccell-easy' => [
        'tech_badge'       => 'SE Technology',
        'tech_name'        => 'CCELL SE Heating Technology',
        'tech_tagline'     => 'The Original CCELL Ceramic. Reliable. Consistent.',
        'tech_description' => [
            'The original CCELL SE Atomizer Platform delivers consistent and smooth vapor thanks to its innovative porous ceramic heating element. Ideal for distillates, the SE platform features thinner walls for rapid heating and reliable performance puff after puff.',
            'As the most cost-effective CCELL platform, the Easy Cart line is built for high-volume wholesale programs where consistent distillate performance and competitive pricing are the top priorities.',
        ],
        'tech_image'       => 'ccell-atomizer-evo.png',
        'tech_image_alt'   => 'CCELL SE Ceramic Atomizer',
        'key_stats' => [
            ['value' => 'SE Ceramic',  'label' => 'Coil Platform'],
            ['value' => 'Best Value',  'label' => 'Price Point'],
            ['value' => 'High-Vol',    'label' => 'Program Ready'],
        ],
        'specs' => [
            'Capacity'         => '0.5 ml / 1.0 ml (varies by model)',
            'Body Material'    => 'Borosilicate glass or Engineering ThermoPlastic (ETP)',
            'Coil Type'        => 'SE ceramic',
            'Resistance'       => '~1.4 &#8486;',
            'Thread'           => '510',
            'Mouthpiece Type'  => 'Snap-fit',
            'Aperture Inlets'  => '4 &times; &#8960;2 mm',
            'Airway'           => 'Stainless steel (Easy Cart) / low-lead brass center post (SE Glass)',
            'Viscosity Range'  => '10,000 – 700,000 cP',
            'Oil Compatibility'=> 'Distillate',
        ],
        'oil_compat' => [
            ['name' => 'Distillate',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Resin',      'icon' => '&#x1F7E1;'],
            ['name' => 'Live Rosin',      'icon' => '&#x1F534;'],
            ['name' => 'Liquid Diamonds', 'icon' => '&#x1F534;'],
        ],
        'oil_note' => 'The SE ceramic coil is optimized for distillate formulations within the 10,000 – 700,000 cP viscosity range. Live resin may work with limited results. For thicker oils like live rosin or liquid diamonds, upgrade to the EVOMAX platform for full viscosity-spectrum support.',
        'materials' => [
            ['icon' => '&#x1F52C;', 'title' => 'Glass or ETP Body',              'desc' => 'Available in borosilicate glass for premium visibility or BPA-free Engineering ThermoPlastic (ETP) for durability and cost savings.'],
            ['icon' => '&#x1F6E1;', 'title' => 'Stainless Steel Airway',         'desc' => 'Easy Cart models use a stainless steel airway. SE Glass (TH2) models use a low-lead brass center post. Both ensure reliable vapor delivery for distillate formulations.'],
            ['icon' => '&#x2705;',  'title' => 'Testing &amp; Compliance',       'desc' => 'Heavy metal tested with FDA-compliant wetted materials. CCELL maintains ISO-certified manufacturing with batch-level traceability.'],
        ],
        'cta_text' => 'Need better performance with thick oils? Compare the EVOMAX upgrade.',
        'cta_link' => '/ccell-heating-technology/',
        'cta_label' => 'Compare All Technologies',
    ],

    // ── Ceramic EVOMAX ──────────────────────────────────────────────────────
    'ccell-ceramic-evo-max' => [
        'tech_badge'       => 'Ceramic EVOMAX',
        'tech_name'        => 'All-Ceramic EVOMAX Technology',
        'tech_tagline'     => 'Zero Metal Contact. Pure Flavor. Premium Build.',
        'tech_description' => [
            'The Ceramic EVOMAX platform combines the advanced oversized ceramic heating element with an all-ceramic body and ceramic airway — eliminating all metal contact with oil for the purest possible flavor profile.',
            'Designed for premium brands that prioritize material purity and clean aesthetics, the full ceramic construction ensures zero metallic interaction with formulations from intake to vapor delivery.',
        ],
        'tech_image'       => 'ccell-atomizer-ceramic-evomax.png',
        'tech_image_alt'   => 'CCELL Ceramic EVOMAX Atomizer',
        'key_stats' => [
            ['value' => 'Zero Metal', 'label' => 'Oil Contact'],
            ['value' => 'EVOMAX',     'label' => 'Coil Platform'],
            ['value' => 'All Oils',   'label' => 'Compatible'],
        ],
        'specs' => [
            'Capacity'         => '0.5 ml / 1.0 ml / 2.0 ml (varies by model)',
            'Body Material'    => 'Full ceramic construction',
            'Coil Type'        => 'EVOMAX oversized ceramic',
            'Resistance'       => '~1.7 &#8486;',
            'Thread'           => '510',
            'Mouthpiece Type'  => 'Snap-fit',
            'Aperture Inlets'  => '4 &times; &#8960;2 mm',
            'Airway'           => 'Ceramic',
            'Viscosity Range'  => '10,000 – 2,000,000 cP',
            'Oil Compatibility'=> 'Distillate, Live Resin, Live Rosin, Liquid Diamonds',
        ],
        'oil_compat' => [
            ['name' => 'Distillate',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Resin',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Rosin',      'icon' => '&#x1F7E2;'],
            ['name' => 'Liquid Diamonds', 'icon' => '&#x1F7E2;'],
        ],
        'oil_note' => 'The all-ceramic oil path with ceramic airway ensures zero metal interaction with any formulation type. Combined with the EVOMAX oversized ceramic element, this platform delivers the purest flavor from any oil — distillate through live rosin and liquid diamonds.',
        'materials' => [
            ['icon' => '&#x1F3FA;', 'title' => 'Full Ceramic Construction',      'desc' => 'The entire oil path — body, airway, mouthpiece, and heating element — is ceramic. Zero metal touches your oil from fill port to mouthpiece.'],
            ['icon' => '&#x1F52C;', 'title' => 'Pharmaceutical-Grade Ceramic',   'desc' => 'High-purity ceramic construction provides chemical inertness, thermal stability, and zero leachables for the cleanest vapor delivery.'],
            ['icon' => '&#x2705;',  'title' => 'Testing &amp; Compliance',       'desc' => 'Heavy metal tested with FDA-compliant wetted materials. CCELL maintains ISO-certified manufacturing with batch-level traceability.'],
        ],
        'cta_text' => 'Looking for the purest oil path? Compare ceramic vs glass cartridges.',
        'cta_link' => '/ccell-heating-technology/',
        'cta_label' => 'Compare All Technologies',
    ],

    // ── 3.0 Postless / Bio-Heating ──────────────────────────────────────────
    'ccell-3-postless' => [
        'tech_badge'       => '3.0 Bio-Heating',
        'tech_name'        => 'CCELL 3.0 Bio-Heating Technology',
        'tech_tagline'     => 'VeinMesh Heating. Stomata Core. Ultra-Low Temp Vaporization.',
        'tech_description' => [
            'CCELL 3.0 Bio-Heating technology features VeinMesh heating combined with a Stomata Core for ultra-low temperature vaporization. The 100% cotton-free, postless design delivers 30% lower atomization temperatures and 10x more consistent micropores than previous generations.',
            'Recommended for distillates and liquid diamonds, the 3.0 platform supports viscosities from 700,000 to 6,000,000 cP. Live resin and live rosin are compatible only if well-filtered with minimal fats and lipids.',
        ],
        'tech_image'       => 'ccell-atomizer-3-postless.png',
        'tech_image_alt'   => 'CCELL 3.0 Postless Atomizer',
        'key_stats' => [
            ['value' => 'VeinMesh',    'label' => 'Heating Element'],
            ['value' => '30% Lower',   'label' => 'Atomization Temp'],
            ['value' => 'Cotton-Free', 'label' => 'Core Design'],
        ],
        'specs' => [
            'Capacity'         => '0.5 ml / 1.0 ml (varies by model)',
            'Body Material'    => 'Borosilicate glass',
            'Heating Element'  => 'VeinMesh heating + Stomata Core',
            'Core'             => '100% cotton-free, postless design',
            'Thread'           => '510',
            'Mouthpiece Type'  => 'Screw-on or press-fit (varies by model)',
            'Design'           => 'Postless (no center post)',
            'Viscosity Range'  => '700,000 – 6,000,000 cP',
            'Oil Compatibility'=> 'Distillate, Liquid Diamonds (Live Resin/Rosin only if well-filtered)',
        ],
        'oil_compat' => [
            ['name' => 'Distillate',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Resin',      'icon' => '&#x1F7E1;'],
            ['name' => 'Live Rosin',      'icon' => '&#x1F7E1;'],
            ['name' => 'Liquid Diamonds', 'icon' => '&#x1F7E2;'],
        ],
        'oil_note' => 'Recommended for distillates and liquid diamonds (700,000 – 6,000,000 cP). Live resin and live rosin are compatible only if well-filtered with minimal fats and lipids. The VeinMesh heating element with Stomata Core delivers 30% lower atomization temperatures and 10x more consistent micropores for clean, efficient vapor production.',
        'materials' => [
            ['icon' => '&#x1F52C;', 'title' => 'Borosilicate Glass Body',        'desc' => 'Premium glass body with full oil visibility. The postless interior creates a clean, open chamber for maximum fill volume.'],
            ['icon' => '&#x1F331;', 'title' => 'VeinMesh + Stomata Core',        'desc' => '100% cotton-free core with VeinMesh heating element and Stomata Core. 10x more consistent micropores deliver ultra-low temperature vaporization with 30% lower atomization temps.'],
            ['icon' => '&#x2705;',  'title' => 'Testing &amp; Compliance',       'desc' => 'Heavy metal tested with FDA-compliant wetted materials. CCELL maintains ISO-certified manufacturing with batch-level traceability.'],
        ],
        'cta_text' => 'Want the newest CCELL technology? See how 3.0 Bio-Heating compares.',
        'cta_link' => '/ccell-heating-technology/',
        'cta_label' => 'Compare All Technologies',
    ],

    // ── AIO EVOMAX ──────────────────────────────────────────────────────────
    'aio-evo-max' => [
        'section_type'     => 'aio',
        'tech_badge'       => 'AIO EVOMAX',
        'tech_name'        => 'AIO EVOMAX — All-In-One Disposable',
        'tech_tagline'     => 'Oversized Ceramic. Built-In Battery. Ready to Fill.',
        'tech_description' => [
            'The AIO EVOMAX platform integrates the advanced EVOMAX oversized ceramic heating element with a rechargeable battery in a single, ready-to-fill disposable unit.',
            'Designed for brands that want top-tier vapor quality in a convenient all-in-one format — no separate battery required. Built for live rosins and liquid diamonds with full viscosity-spectrum support.',
        ],
        'tech_image'       => 'ccell-aio-evomax.png',
        'tech_image_alt'   => 'CCELL AIO EVOMAX Disposable',
        'key_stats' => [
            ['value' => 'EVOMAX',      'label' => 'Coil Type'],
            ['value' => 'Rechargeable','label' => 'Battery'],
            ['value' => 'All Oils',    'label' => 'Compatible'],
        ],
        'specs' => [
            'Form Factor'      => 'All-in-one disposable',
            'Coil Type'        => 'EVOMAX oversized ceramic',
            'Activation'       => 'Draw-activated (varies by model)',
            'Battery'          => 'Built-in rechargeable lithium-ion',
            'Charging'         => 'USB-C',
            'Capacity'         => 'Varies by model (0.5ml – 2.0ml)',
            'Viscosity Range'  => '10,000 – 2,000,000 cP',
            'Oil Compatibility'=> 'Distillate, Live Resin, Live Rosin, Liquid Diamonds',
        ],
        'oil_compat' => [
            ['name' => 'Distillate',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Resin',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Rosin',      'icon' => '&#x1F7E2;'],
            ['name' => 'Liquid Diamonds', 'icon' => '&#x1F7E2;'],
        ],
        'oil_note' => 'The integrated EVOMAX coil handles the full viscosity spectrum in a convenient disposable format. Recommended for live rosins and liquid diamonds. No separate battery purchase required — fill, cap, and ship.',
        'materials' => [
            ['icon' => '&#x1F50B;', 'title' => 'Rechargeable Battery',           'desc' => 'Built-in lithium-ion battery with USB-C charging ensures users can consume the full oil volume without power concerns.'],
            ['icon' => '&#x1F6E1;', 'title' => 'Food-Grade Materials',           'desc' => 'Oil-contact components use food-grade and medical-grade materials for safe, clean vapor delivery.'],
            ['icon' => '&#x2705;',  'title' => 'Testing &amp; Compliance',       'desc' => 'Heavy metal tested with FDA-compliant wetted materials. CCELL maintains ISO-certified manufacturing with batch-level traceability.'],
        ],
        'cta_text' => 'Comparing AIO disposables? See all platforms side by side.',
        'cta_link' => '/ccell-heating-technology/',
        'cta_label' => 'Compare All Platforms',
    ],

    // ── AIO SE (Standard Edition) ────────────────────────────────────────────
    'aio-se-standard' => [
        'section_type'     => 'aio',
        'tech_badge'       => 'AIO SE',
        'tech_name'        => 'AIO SE — Standard Edition Disposable',
        'tech_tagline'     => 'Reliable Performance. Value Pricing. Distillate Optimized.',
        'tech_description' => [
            'The AIO SE (Standard Edition) platform uses the original CCELL SE ceramic heating element in an affordable all-in-one disposable format optimized for distillate formulations.',
            'The most cost-effective AIO option, the SE line is ideal for high-volume distillate programs where consistent performance and competitive pricing drive purchasing decisions.',
        ],
        'tech_image'       => 'ccell-aio-se.png',
        'tech_image_alt'   => 'CCELL AIO SE Disposable',
        'key_stats' => [
            ['value' => 'SE Ceramic',  'label' => 'Coil Type'],
            ['value' => 'Best Value',  'label' => 'Price Point'],
            ['value' => 'Distillate',  'label' => 'Optimized'],
        ],
        'specs' => [
            'Form Factor'      => 'All-in-one disposable',
            'Coil Type'        => 'SE ceramic',
            'Activation'       => 'Draw-activated',
            'Battery'          => 'Built-in (rechargeable on select models)',
            'Capacity'         => 'Varies by model (0.5ml – 2.0ml)',
            'Viscosity Range'  => '10,000 – 700,000 cP',
            'Oil Compatibility'=> 'Distillate',
        ],
        'oil_compat' => [
            ['name' => 'Distillate',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Resin',      'icon' => '&#x1F7E1;'],
            ['name' => 'Live Rosin',      'icon' => '&#x1F534;'],
            ['name' => 'Liquid Diamonds', 'icon' => '&#x1F534;'],
        ],
        'oil_note' => 'The SE ceramic element is optimized for distillate formulations within the 10,000 – 700,000 cP viscosity range. For thicker formulations like live rosin or liquid diamonds, consider the AIO EVOMAX or AIO 3.0 Bio-Heating platform.',
        'materials' => [
            ['icon' => '&#x1F50B;', 'title' => 'Built-In Battery',              'desc' => 'Integrated battery sized to fully consume the oil volume. Select models include USB-C rechargeable battery.'],
            ['icon' => '&#x1F6E1;', 'title' => 'Food-Grade Materials',           'desc' => 'Oil-contact components use food-grade materials for safe vapor delivery across distillate formulations.'],
            ['icon' => '&#x2705;',  'title' => 'Testing &amp; Compliance',       'desc' => 'Heavy metal tested with FDA-compliant wetted materials. CCELL maintains ISO-certified manufacturing with batch-level traceability.'],
        ],
        'cta_text' => 'Need better oil compatibility? Compare the AIO EVOMAX upgrade.',
        'cta_link' => '/ccell-heating-technology/',
        'cta_label' => 'Compare All Platforms',
    ],

    // ── AIO 3.0 Bio-Heating ─────────────────────────────────────────────────
    'aio-3-bio-heating' => [
        'section_type'     => 'aio',
        'tech_badge'       => 'AIO 3.0 Bio-Heating',
        'tech_name'        => 'AIO 3.0 — Bio-Heating Disposable',
        'tech_tagline'     => 'VeinMesh + Stomata Core. Postless Chamber. Ultra-Low Temp.',
        'tech_description' => [
            'The AIO 3.0 Bio-Heating platform integrates VeinMesh heating with a Stomata Core in a postless, all-in-one disposable format. The 100% cotton-free core delivers 30% lower atomization temperatures and 10x more consistent micropores.',
            'Recommended for distillates and liquid diamonds, with live resin/rosin compatibility when well-filtered. The postless architecture maximizes oil capacity while the ultra-low temperature vaporization preserves terpene profiles.',
        ],
        'tech_image'       => 'ccell-aio-3-bioheating.png',
        'tech_image_alt'   => 'CCELL AIO 3.0 Bio-Heating Disposable',
        'key_stats' => [
            ['value' => 'VeinMesh',    'label' => 'Heating Element'],
            ['value' => '30% Lower',   'label' => 'Atomization Temp'],
            ['value' => 'Cotton-Free', 'label' => 'Core Design'],
        ],
        'specs' => [
            'Form Factor'      => 'All-in-one disposable',
            'Heating Element'  => 'VeinMesh heating + Stomata Core',
            'Core'             => '100% cotton-free, postless design',
            'Activation'       => 'Draw-activated',
            'Battery'          => 'Built-in rechargeable lithium-ion',
            'Charging'         => 'USB-C',
            'Capacity'         => 'Varies by model',
            'Viscosity Range'  => '700,000 – 6,000,000 cP',
            'Oil Compatibility'=> 'Distillate, Liquid Diamonds (Live Resin/Rosin only if well-filtered)',
        ],
        'oil_compat' => [
            ['name' => 'Distillate',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Resin',      'icon' => '&#x1F7E1;'],
            ['name' => 'Live Rosin',      'icon' => '&#x1F7E1;'],
            ['name' => 'Liquid Diamonds', 'icon' => '&#x1F7E2;'],
        ],
        'oil_note' => 'Recommended for distillates and liquid diamonds (700,000 – 6,000,000 cP). Live resin and live rosin are compatible only if well-filtered with minimal fats and lipids. The VeinMesh + Stomata Core delivers ultra-low temp vaporization in a compact disposable form factor.',
        'materials' => [
            ['icon' => '&#x1F50B;', 'title' => 'Rechargeable Battery',           'desc' => 'Built-in lithium-ion battery with USB-C charging ensures complete oil consumption with reliable power delivery.'],
            ['icon' => '&#x1F331;', 'title' => 'VeinMesh + Stomata Core',        'desc' => '100% cotton-free core with VeinMesh heating and Stomata Core. 10x more consistent micropores for ultra-low temperature vaporization with 30% lower atomization temps.'],
            ['icon' => '&#x2705;',  'title' => 'Testing &amp; Compliance',       'desc' => 'Heavy metal tested with FDA-compliant wetted materials. CCELL maintains ISO-certified manufacturing with batch-level traceability.'],
        ],
        'cta_text' => 'Exploring the latest CCELL tech? See how 3.0 compares to EVOMAX.',
        'cta_link' => '/ccell-heating-technology/',
        'cta_label' => 'Compare All Platforms',
    ],

    // ── Pod Systems ──────────────────────────────────────────────────────────
    'pod-systems' => [
        'section_type'     => 'pod',
        'tech_badge'       => 'Pod System',
        'tech_name'        => 'CCELL Pod System Platform',
        'tech_tagline'     => 'Replaceable Pods. Rechargeable Device. CCELL Heating.',
        'tech_description' => [
            'CCELL Pod Systems combine a rechargeable battery device with replaceable pod cartridges, giving brands and consumers the flexibility of a refillable system with the reliability of CCELL ceramic heating technology.',
            'The magnetic or snap-fit pod connection allows quick, clean cartridge swaps. Each pod uses a CCELL ceramic element for consistent flavor and vapor quality across the full pod lifecycle.',
        ],
        'tech_image'       => 'ccell-pod-system.png',
        'tech_image_alt'   => 'CCELL Pod System',
        'key_stats' => [
            ['value' => 'CCELL Ceramic', 'label' => 'Heating Tech'],
            ['value' => 'Rechargeable',  'label' => 'Battery'],
            ['value' => 'Replaceable',   'label' => 'Pod Design'],
        ],
        'specs' => [
            'Form Factor'      => 'Pod system (battery + replaceable pods)',
            'Heating Element'  => 'CCELL ceramic',
            'Pod Connection'   => 'Magnetic or snap-fit (varies by model)',
            'Battery'          => 'Built-in rechargeable lithium-ion',
            'Charging'         => 'USB-C',
            'Activation'       => 'Draw-activated or button-activated',
            'Pod Capacity'     => 'Varies by model (0.5ml – 2.0ml)',
            'Compatibility'    => 'CCELL pod ecosystem',
        ],
        'oil_compat' => [
            ['name' => 'Distillate',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Resin',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Rosin',      'icon' => '&#x1F7E1;'],
            ['name' => 'Liquid Diamonds', 'icon' => '&#x1F7E1;'],
        ],
        'oil_note' => 'Oil compatibility depends on the specific pod cartridge installed. Standard CCELL ceramic pods handle distillate and live resin. Check pod specifications for thicker formulation support.',
        'materials' => [
            ['icon' => '&#x1F50B;', 'title' => 'Rechargeable Battery',           'desc' => 'Built-in lithium-ion battery with USB-C charging. Battery outlasts multiple pod changes for convenient daily use.'],
            ['icon' => '&#x1F517;', 'title' => 'Secure Pod Connection',          'desc' => 'Magnetic or snap-fit pod attachment ensures a reliable connection with no threading required. Quick, clean pod swaps.'],
            ['icon' => '&#x2705;',  'title' => 'Testing &amp; Compliance',       'desc' => 'CCELL maintains ISO-certified manufacturing with batch-level traceability for both battery devices and pod cartridges.'],
        ],
        'cta_text' => 'Considering pods vs cartridges? Compare form factors for your brand.',
        'cta_link' => '/ccell-heating-technology/',
        'cta_label' => 'Compare All Platforms',
    ],

    // ── 510 Batteries / Power Supplies ───────────────────────────────────────
    'ccell-batteries' => [
        'section_type'     => 'battery',
        'tech_badge'       => '510 Battery',
        'tech_name'        => 'CCELL 510-Thread Power Supply',
        'tech_tagline'     => 'Universal 510 Connection. Optimized for CCELL Cartridges.',
        'tech_description' => [
            'CCELL 510-thread batteries are purpose-built power supplies designed to deliver optimal voltage and current for CCELL ceramic heating elements. While compatible with any standard 510 cartridge, they are tuned for the best performance with CCELL coils.',
            'The lineup includes: Palm SE (500mAh, 3 voltage settings, 15s preheat), M4 Tiny (200mAh, 14mm ultra-compact), M4B Pro (290mAh, 3 voltage settings, tamper-proof), Kap (500mAh, magnetic sleeve, 3 voltage settings), Stylo (500mAh, 3 voltage settings), Go Stik (280mAh, 2 voltage settings), Palm Pro (500mAh, adjustable airflow, magnetic 510 adapter), and Fino (190mAh portable + 1000mAh dock, variable voltage).',
        ],
        'tech_image'       => 'ccell-510-battery.png',
        'tech_image_alt'   => 'CCELL 510 Battery',
        'key_stats' => [
            ['value' => '510 Thread',  'label' => 'Connection'],
            ['value' => 'USB-C',       'label' => 'Charging'],
            ['value' => 'CCELL Tuned', 'label' => 'Optimization'],
        ],
        'specs' => [
            'Connection'       => '510 thread (universal)',
            'Battery Type'     => 'Built-in rechargeable lithium-ion',
            'Charging'         => 'USB-C (all models)',
            'Battery Capacity' => '190–500 mAh (varies by model; Fino dock: 1000 mAh)',
            'Voltage'          => 'Variable: 2.2V–3.6V range depending on model',
            'Activation'       => 'Inhale-activated (all models)',
            'Preheat'          => '10–15 second preheat (available on Palm SE, M4B Pro, Kap, Stylo, Palm Pro, Fino)',
            'Notable Features' => 'Palm Pro: adjustable airflow + magnetic 510 adapter; Kap: magnetic sleeve; M4B Pro: tamper-proof; Fino: portable + dock system',
            'Compatibility'    => 'All standard 510 cartridges',
        ],
        'oil_compat' => [
            ['name' => 'Distillate',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Resin',      'icon' => '&#x1F7E2;'],
            ['name' => 'Live Rosin',      'icon' => '&#x1F7E2;'],
            ['name' => 'Liquid Diamonds', 'icon' => '&#x1F7E2;'],
        ],
        'oil_note' => 'CCELL 510 batteries are compatible with all standard 510 cartridges and all oil types. Variable-voltage models allow users to optimize temperature for different viscosities and formulations. All models charge via USB-C and feature inhale activation.',
        'materials' => [
            ['icon' => '&#x1F50B;', 'title' => 'Lithium-Ion Battery',            'desc' => 'High-quality lithium-ion cells (190–500 mAh) with multiple safety protections: short circuit, over-charge, over-discharge, and over-temperature. Fino includes a 1000 mAh charging dock.'],
            ['icon' => '&#x26A1;',  'title' => 'Optimized Power Delivery',       'desc' => 'Voltage profiles tuned for CCELL ceramic elements. Models offer 2–3 voltage settings (e.g., Palm SE: 2.8V/3.2V/3.6V, Kap: 2.6V/3.0V/3.4V, Stylo: 2.4V/2.8V/3.2V, Fino: 2.2V–3.6V variable).'],
            ['icon' => '&#x2705;',  'title' => 'Safety &amp; Certifications',    'desc' => 'UL-listed or CE-certified (varies by model). Built-in safety features include auto-shutoff, low-voltage protection, and thermal cutoff.'],
        ],
        'cta_text' => 'Need help choosing the right battery for your cartridge program?',
        'cta_link' => '/request-samples/',
        'cta_label' => 'Talk to a Sales Rep',
    ],
];

// =============================================================================
// Render Function — outputs all 5 informational section blocks
// =============================================================================
function render_pdp_sections($tier_slug) {
    global $pdp_tier_data, $product;

    if (!isset($pdp_tier_data[$tier_slug])) {
        return;
    }

    $t = $pdp_tier_data[$tier_slug];
    $sku = ($product && method_exists($product, 'get_sku')) ? $product->get_sku() : '';

    // Build image URL — check uploads/2026/02/ first
    $image_url = content_url('/uploads/2026/02/' . $t['tech_image']);
    ?>
    <!-- a. Technology Overview -->
    <div class="pdp-section pdp-section-dark">
        <div class="pdp-tech">
            <div class="pdp-tech-text">
                <span class="pdp-badge"><?php echo esc_html($t['tech_badge']); ?></span>
                <h2><?php echo esc_html($t['tech_name']); ?></h2>
                <div class="pdp-tagline"><?php echo esc_html($t['tech_tagline']); ?></div>
                <?php foreach ($t['tech_description'] as $para): ?>
                    <p><?php echo esc_html($para); ?></p>
                <?php endforeach; ?>
                <div class="pdp-tech-stats">
                    <?php foreach ($t['key_stats'] as $stat): ?>
                        <div class="pdp-stat">
                            <span class="pdp-stat-value"><?php echo esc_html($stat['value']); ?></span>
                            <span class="pdp-stat-label"><?php echo esc_html($stat['label']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="pdp-tech-img">
                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($t['tech_image_alt']); ?>" width="280" height="280" loading="lazy">
            </div>
        </div>
    </div>

    <!-- b. Specifications Table -->
    <div class="pdp-section pdp-section-light">
        <div class="pdp-specs-header">
            <h2>Technical Specifications</h2>
            <?php if ($sku): ?>
                <p>SKU: <?php echo esc_html($sku); ?></p>
            <?php endif; ?>
        </div>
        <table class="pdp-specs-table">
            <?php foreach ($t['specs'] as $label => $value): ?>
                <tr><td><?php echo esc_html($label); ?></td><td><?php echo $value; ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- c. Oil Compatibility -->
    <div class="pdp-section pdp-section-dark">
        <div class="pdp-oil-header">
            <h2>Oil Compatibility</h2>
            <p><?php echo esc_html($t['tech_badge']); ?> — supported oil types</p>
        </div>
        <div class="pdp-oil-grid">
            <?php foreach ($t['oil_compat'] as $oil): ?>
                <div class="pdp-oil-card">
                    <div class="pdp-oil-icon"><?php echo $oil['icon']; ?></div>
                    <div class="pdp-oil-name"><?php echo esc_html($oil['name']); ?></div>
                    <div class="pdp-oil-check"><?php
                        // Green circle = Compatible, Yellow = Limited, Red = Not recommended
                        if ($oil['icon'] === '&#x1F7E2;') {
                            echo '&#10003; Compatible';
                        } elseif ($oil['icon'] === '&#x1F7E1;') {
                            echo '&#x26A0; Limited';
                        } else {
                            echo '&#x2717; Not Recommended';
                        }
                    ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="pdp-oil-note">
            <?php echo esc_html($t['oil_note']); ?>
        </div>
    </div>

    <!-- d. Materials & Compliance -->
    <div class="pdp-section pdp-section-white">
        <div class="pdp-compliance-header">
            <h2>Materials &amp; Compliance</h2>
            <p>Built for brands that prioritize safety, quality, and regulatory readiness</p>
        </div>
        <div class="pdp-compliance-grid">
            <?php foreach ($t['materials'] as $mat): ?>
                <div class="pdp-compliance-card">
                    <div class="pdp-compliance-icon"><?php echo $mat['icon']; ?></div>
                    <h4><?php echo $mat['title']; ?></h4>
                    <p><?php echo $mat['desc']; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- e. Compare Platforms CTA -->
    <div class="pdp-section pdp-section-light">
        <div class="pdp-compare-bar">
            <p><?php echo esc_html($t['cta_text']); ?></p>
            <a href="<?php echo esc_url($t['cta_link']); ?>"><?php echo esc_html($t['cta_label']); ?></a>
            <a href="/request-samples/" class="pdp-btn-outline">Talk to a Sales Rep</a>
        </div>
    </div>
    <?php
}

// =============================================================================
// CSS — shared across all tiers (output once)
// =============================================================================
function render_pdp_sections_css() {
    static $css_rendered = false;
    if ($css_rendered) return;
    $css_rendered = true;
    ?>
    <style>
    /* ── PDP Informational Sections ── */
    .pdp-evomax-wrap{max-width:1200px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
    .pdp-evomax-wrap *{box-sizing:border-box}

    /* Section rhythm: alternating backgrounds */
    .pdp-section{padding:60px 30px}
    .pdp-section-dark{background:#1a1a1a;color:#fff}
    .pdp-section-light{background:#f8f8f8;color:#1a1a1a}
    .pdp-section-white{background:#fff;color:#1a1a1a}

    /* ── a. Technology Overview ── */
    .pdp-tech{display:flex;gap:50px;align-items:center;flex-wrap:wrap}
    .pdp-tech-text{flex:1;min-width:300px}
    .pdp-tech-text .pdp-badge{display:inline-block;background:#E50914;color:#fff;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:5px 14px;border-radius:3px;margin-bottom:16px}
    .pdp-tech-text h2{font-size:30px;font-weight:800;margin:0 0 8px;line-height:1.2}
    .pdp-tech-text .pdp-tagline{font-size:16px;margin:0 0 20px;font-weight:500}
    .pdp-section-dark .pdp-tech-text .pdp-tagline{color:#aaa}
    .pdp-section-light .pdp-tech-text .pdp-tagline{color:#666}
    .pdp-tech-text p{font-size:15px;line-height:1.7;margin:0 0 14px}
    .pdp-section-dark .pdp-tech-text p{color:#ccc}
    .pdp-section-light .pdp-tech-text p{color:#444}
    .pdp-tech-stats{display:flex;gap:30px;margin-top:24px;flex-wrap:wrap}
    .pdp-stat{text-align:center}
    .pdp-stat-value{display:block;font-size:22px;font-weight:800;color:#E50914}
    .pdp-stat-label{font-size:12px;color:#999;text-transform:uppercase;letter-spacing:0.5px;margin-top:4px}
    .pdp-tech-img{flex:0 0 280px;text-align:center}
    .pdp-tech-img img{max-width:100%;height:auto;filter:drop-shadow(0 10px 30px rgba(0,0,0,0.3))}

    /* ── b. Specifications Table ── */
    .pdp-specs-header{text-align:center;margin-bottom:35px}
    .pdp-specs-header h2{font-size:26px;font-weight:800;margin:0 0 8px}
    .pdp-specs-header p{font-size:14px;color:#888;margin:0}
    .pdp-specs-table{width:100%;max-width:700px;margin:0 auto;border-collapse:collapse}
    .pdp-specs-table tr{border-bottom:1px solid #eee}
    .pdp-specs-table td{padding:14px 16px;font-size:14px;vertical-align:top}
    .pdp-specs-table td:first-child{font-weight:600;color:#1a1a1a;width:40%;white-space:nowrap}
    .pdp-specs-table td:last-child{color:#555}

    /* ── c. Oil Compatibility ── */
    .pdp-oil-header{text-align:center;margin-bottom:35px}
    .pdp-oil-header h2{font-size:26px;font-weight:800;margin:0 0 8px;color:#fff}
    .pdp-oil-header p{font-size:14px;color:#aaa;margin:0}
    .pdp-oil-grid{display:flex;gap:20px;justify-content:center;flex-wrap:wrap;margin-bottom:30px}
    .pdp-oil-card{background:#222;border:1px solid #333;border-radius:10px;padding:28px 24px;text-align:center;min-width:160px;flex:1;max-width:220px}
    .pdp-oil-icon{font-size:32px;margin-bottom:10px}
    .pdp-oil-name{font-size:16px;font-weight:700;margin:0 0 6px;color:#fff}
    .pdp-oil-check{color:#22c55e;font-weight:700;font-size:14px}
    .pdp-oil-card .pdp-oil-check-limited{color:#eab308}
    .pdp-oil-card .pdp-oil-check-no{color:#ef4444}
    .pdp-oil-note{max-width:700px;margin:0 auto;text-align:center;font-size:14px;line-height:1.7;color:#aaa}

    /* ── d. Materials & Compliance ── */
    .pdp-compliance-header{text-align:center;margin-bottom:35px}
    .pdp-compliance-header h2{font-size:26px;font-weight:800;margin:0 0 8px}
    .pdp-compliance-header p{font-size:14px;color:#888;margin:0}
    .pdp-compliance-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;max-width:900px;margin:0 auto}
    .pdp-compliance-card{background:#f0f0f0;border-radius:10px;padding:28px 22px;text-align:center}
    .pdp-compliance-icon{font-size:28px;margin-bottom:12px;color:#E50914}
    .pdp-compliance-card h4{font-size:15px;font-weight:700;margin:0 0 8px;color:#1a1a1a}
    .pdp-compliance-card p{font-size:13px;color:#555;line-height:1.6;margin:0}

    /* ── e. Compare CTA ── */
    .pdp-compare-bar{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,#1a1a1a,#2d2d2d);border-radius:12px;padding:28px 35px;max-width:900px;margin:0 auto;color:#fff;flex-wrap:wrap;gap:15px}
    .pdp-compare-bar p{font-size:16px;font-weight:600;margin:0;flex:1}
    .pdp-compare-bar a{display:inline-block;background:#E50914;color:#fff;font-size:14px;font-weight:600;padding:12px 28px;border-radius:6px;text-decoration:none;transition:background .2s;white-space:nowrap}
    .pdp-compare-bar a:hover{background:#c30812;color:#fff}
    .pdp-compare-bar .pdp-btn-outline{background:transparent;border:2px solid #fff;color:#fff}
    .pdp-compare-bar .pdp-btn-outline:hover{background:#fff;color:#1a1a1a}

    /* ── Sales rep button (add-to-cart area) ── */
    .pdp-sales-cta{margin-top:14px}
    .pdp-sales-cta a{display:inline-block;background:transparent;color:#1a1a1a;border:2px solid #1a1a1a;font-size:14px;font-weight:600;padding:11px 24px;border-radius:6px;text-decoration:none;transition:all .2s;text-align:center;width:100%}
    .pdp-sales-cta a:hover{background:#1a1a1a;color:#fff}

    /* ── Responsive ── */
    @media(max-width:768px){
        .pdp-section{padding:40px 20px}
        .pdp-tech{flex-direction:column;text-align:center}
        .pdp-tech-img{flex:0 0 auto;order:-1}
        .pdp-tech-stats{justify-content:center}
        .pdp-compliance-grid{grid-template-columns:1fr}
        .pdp-compare-bar{flex-direction:column;text-align:center}
        .pdp-specs-table td:first-child{width:45%}
    }
    @media(max-width:480px){
        .pdp-tech-text h2{font-size:24px}
        .pdp-oil-grid{flex-direction:column;align-items:center}
        .pdp-oil-card{max-width:100%;width:100%}
        .pdp-specs-header h2,
        .pdp-oil-header h2,
        .pdp-compliance-header h2{font-size:22px}
    }
    </style>
    <?php
}

// =============================================================================
// Hook: Informational sections below product summary (priority 6)
// Detects which tier the current product belongs to and renders sections
// =============================================================================
add_action('woocommerce_after_single_product_summary', function () {
    global $product, $pdp_tier_data;

    if (!$product) {
        return;
    }

    // Check each tier slug — first match wins
    // Technology subcategory slugs are checked first, then fallback
    // to parent categories for batteries (542) and pods (1050)
    $matched_tier = null;
    foreach (array_keys($pdp_tier_data) as $slug) {
        if (has_term($slug, 'product_cat')) {
            $matched_tier = $slug;
            break;
        }
    }

    // Fallback: battery products → ccell-batteries tier
    if (!$matched_tier && has_term('batteries', 'product_cat')) {
        $matched_tier = 'ccell-batteries';
    }
    // Fallback: pod products → pod-systems tier
    if (!$matched_tier && has_term('pod-systems', 'product_cat')) {
        $matched_tier = 'pod-systems';
    }

    if (!$matched_tier) {
        return;
    }

    // Output CSS once, then the wrapper + sections
    render_pdp_sections_css();
    echo '<div class="pdp-evomax-wrap">';
    render_pdp_sections($matched_tier);
    echo '</div>';
}, 6);

// =============================================================================
// Hook: "Talk to a Sales Rep" button below add-to-cart
// Applies to all cartridge and AIO products that have any tier assigned
// =============================================================================
add_action('woocommerce_after_add_to_cart_form', function () {
    global $pdp_tier_data;

    $has_tier = false;
    foreach (array_keys($pdp_tier_data) as $slug) {
        if (has_term($slug, 'product_cat')) {
            $has_tier = true;
            break;
        }
    }
    // Also show for battery and pod products
    if (!$has_tier && (has_term('batteries', 'product_cat') || has_term('pod-systems', 'product_cat'))) {
        $has_tier = true;
    }

    if (!$has_tier) {
        return;
    }
    ?>
    <div class="pdp-sales-cta">
        <a href="/request-samples/">Talk to a Sales Rep</a>
    </div>
    <?php
});
