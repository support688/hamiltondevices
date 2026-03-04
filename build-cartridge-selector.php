<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// Build the visual technology selector HTML for the cartridge category description
$selector_html = '
<style>
.cart-selector-wrap {
    max-width:1200px;
    margin:0 auto 40px;
    padding:0 15px;
}
.cart-selector-title {
    text-align:center;
    margin-bottom:30px;
}
.cart-selector-title h2 {
    font-size:28px;
    font-weight:700;
    color:#1a1a1a;
    margin:0 0 8px;
}
.cart-selector-title p {
    font-size:16px;
    color:#666;
    max-width:650px;
    margin:0 auto;
    line-height:1.6;
}
.cart-tech-grid {
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:16px;
    margin-bottom:20px;
}
@media (max-width:768px) {
    .cart-tech-grid { grid-template-columns:repeat(2, 1fr); }
}
.cart-tech-card {
    background:#fff;
    border-radius:12px;
    overflow:hidden;
    text-align:center;
    box-shadow:0 2px 15px rgba(0,0,0,0.06);
    border:2px solid transparent;
    transition:all 0.3s ease;
    text-decoration:none;
    display:block;
    color:inherit;
}
.cart-tech-card:hover {
    border-color:#c72035;
    box-shadow:0 6px 30px rgba(0,0,0,0.12);
    transform:translateY(-3px);
    text-decoration:none;
    color:inherit;
}
.cart-tech-card .card-badge {
    display:inline-block;
    padding:4px 12px;
    border-radius:20px;
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.5px;
    margin-top:15px;
}
.cart-tech-card .badge-economy { background:#e8f5e9; color:#2e7d32; }
.cart-tech-card .badge-premium { background:#e3f2fd; color:#1565c0; }
.cart-tech-card .badge-ceramic { background:#fce4ec; color:#c62828; }
.cart-tech-card .badge-nextgen { background:#f3e5f5; color:#6a1b9a; }
.cart-tech-card .card-img-area {
    padding:25px 20px 15px;
    background:linear-gradient(180deg, #fafafa 0%, #fff 100%);
    min-height:140px;
    display:flex;
    align-items:center;
    justify-content:center;
}
.cart-tech-card .card-img-area img {
    max-height:110px;
    max-width:80%;
    object-fit:contain;
}
.cart-tech-card .card-content {
    padding:15px 18px 22px;
    border-top:1px solid #f0f0f0;
}
.cart-tech-card .card-content h3 {
    font-size:16px;
    font-weight:700;
    margin:0 0 4px;
    color:#1a1a1a;
}
.cart-tech-card .card-content .coil-name {
    font-size:12px;
    color:#999;
    margin:0 0 8px;
    text-transform:uppercase;
    letter-spacing:0.5px;
}
.cart-tech-card .card-content p {
    font-size:13px;
    color:#666;
    line-height:1.5;
    margin:0 0 10px;
}
.cart-tech-card .card-specs {
    display:flex;
    justify-content:center;
    gap:8px;
    flex-wrap:wrap;
}
.cart-tech-card .spec-tag {
    background:#f5f5f5;
    padding:3px 8px;
    border-radius:4px;
    font-size:11px;
    color:#555;
    font-weight:500;
}
.cart-tech-card .card-cta {
    color:#c72035;
    font-size:13px;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:0.3px;
    margin-top:12px;
    display:inline-block;
}
.cart-compare-bar {
    background:#f8f8f8;
    border-radius:8px;
    padding:18px 25px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:12px;
}
.cart-compare-bar p {
    margin:0;
    font-size:14px;
    color:#555;
}
.cart-compare-bar a {
    background:#c72035;
    color:#fff;
    padding:10px 24px;
    border-radius:4px;
    font-weight:600;
    font-size:14px;
    text-decoration:none;
    transition:background 0.2s;
}
.cart-compare-bar a:hover {
    background:#a01a2c;
    color:#fff;
    text-decoration:none;
}
</style>

<div class="cart-selector-wrap">
    <div class="cart-selector-title">
        <h2>Choose Your Cartridge Technology</h2>
        <p>CCELL offers four cartridge platforms, each engineered for different performance requirements and price points. Select a technology to view available configurations.</p>
    </div>

    <div class="cart-tech-grid">
        <a href="/product-category/cartridge/ccell-easy/" class="cart-tech-card">
            <span class="card-badge badge-economy">Best Value</span>
            <div class="card-img-area">
                <img src="/wp-content/uploads/2024/09/hamilton_devices_ccell_th210-y_cartridge_-_black.png" alt="CCELL Easy Cartridge">
            </div>
            <div class="card-content">
                <h3>CCELL Easy</h3>
                <div class="coil-name">EVO Heating Coil</div>
                <p>Reliable performance at the best price point. Ideal for high-volume programs.</p>
                <div class="card-specs">
                    <span class="spec-tag">Glass (TH2)</span>
                    <span class="spec-tag">Poly (M6T)</span>
                    <span class="spec-tag">Snap-Fit</span>
                </div>
                <div class="card-cta">View Products &rarr;</div>
            </div>
        </a>

        <a href="/product-category/cartridge/ccell-evo-max/" class="cart-tech-card">
            <span class="card-badge badge-premium">Premium</span>
            <div class="card-img-area">
                <img src="/wp-content/uploads/2024/09/hamilton_devices_ccell_th210-y_cartridge_-_black.png" alt="CCELL EVO MAX Cartridge">
            </div>
            <div class="card-content">
                <h3>CCELL EVO MAX</h3>
                <div class="coil-name">EVO MAX Heating Coil</div>
                <p>Advanced coil technology for superior vapor quality and oil efficiency.</p>
                <div class="card-specs">
                    <span class="spec-tag">Glass (TH2)</span>
                    <span class="spec-tag">Poly (M6T)</span>
                    <span class="spec-tag">Snap-Fit</span>
                </div>
                <div class="card-cta">View Products &rarr;</div>
            </div>
        </a>

        <a href="/product-category/cartridge/ccell-ceramic-evo-max/" class="cart-tech-card">
            <span class="card-badge badge-ceramic">Ceramic Body</span>
            <div class="card-img-area">
                <img src="/wp-content/uploads/2024/09/hamilton_devices_ccell_th210-y_cartridge_-_black.png" alt="CCELL Ceramic EVO MAX Cartridge">
            </div>
            <div class="card-content">
                <h3>Ceramic EVO MAX</h3>
                <div class="coil-name">EVO MAX Heating Coil</div>
                <p>Full ceramic body eliminates metal contact with oil for the purest experience.</p>
                <div class="card-specs">
                    <span class="spec-tag">Ceramic Body</span>
                    <span class="spec-tag">Snap-Fit</span>
                </div>
                <div class="card-cta">View Products &rarr;</div>
            </div>
        </a>

        <a href="/product-category/cartridge/ccell-3-postless/" class="cart-tech-card">
            <span class="card-badge badge-nextgen">Next-Gen</span>
            <div class="card-img-area">
                <img src="/wp-content/uploads/2024/09/hamilton_devices_ccell_th210-y_cartridge_-_black.png" alt="CCELL 3.0 Postless Cartridge">
            </div>
            <div class="card-content">
                <h3>CCELL 3.0 Postless</h3>
                <div class="coil-name">CCELL 3.0 Heating Technology</div>
                <p>Postless design for the cleanest oil path and easiest filling. The Klean series.</p>
                <div class="card-specs">
                    <span class="spec-tag">Postless</span>
                    <span class="spec-tag">Snap-Fit</span>
                </div>
                <div class="card-cta">View Products &rarr;</div>
            </div>
        </a>
    </div>

    <div class="cart-compare-bar">
        <p><strong>Not sure which technology is right for your brand?</strong> Our team can help you choose the right cartridge for your formulation and program size.</p>
        <a href="/request-samples/">Request Samples</a>
    </div>
</div>
';

// Update the cartridge category description (term_taxonomy for term_id 543)
$stmt = $conn->prepare("UPDATE wp_term_taxonomy SET description=? WHERE term_id=543 AND taxonomy='product_cat'");
$stmt->bind_param('s', $selector_html);
$stmt->execute();
echo "Updated cartridge category description. Affected rows: " . $stmt->affected_rows . "\n";

// Also hide the old sub-categories from displaying (they still exist for existing product URLs)
// We want the new technology categories to be the prominent ones

echo "Done!\n";
$conn->close();
