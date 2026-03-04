<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

$result = $conn->query("SELECT post_content FROM wp_posts WHERE ID=96");
$row = $result->fetch_assoc();
$content = $row['post_content'];

$old_cta = '[section bg_color="rgb(199,32,53)" padding="50px"]
[row]
[col span__sm="12" align="center"]
[ux_text font_size="1.6" text_align="center" text_color="rgb(255,255,255)"]
<h2 style="color:#fff;margin-bottom:10px;">Ready to Evaluate CCELL® for Your Brand?</h2>
[/ux_text]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<p style="font-size:18px;color:rgba(255,255,255,0.9);max-width:600px;margin:0 auto 25px;">Request complimentary samples and connect with our wholesale team. We will help you find the right hardware for your product line.</p>
[/ux_text]
[button text="Request Samples" color="white" style="outline" size="larger" link="request-samples"]
[button text="Contact Our Team" color="white" size="larger" link="contact"]
[/col]
[/row]
[/section]';

$new_cta = '[section padding="0px"]
[row width="full-width" padding="0px"]
[col span__sm="12" padding="0px"]
<div style="background:linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%);padding:80px 40px;text-align:center;">
<p style="text-transform:uppercase;letter-spacing:5px;color:#c72035;font-weight:700;font-size:12px;margin-bottom:20px;">Start Your Partnership</p>
<h2 style="color:#fff;font-size:36px;font-weight:700;margin:0 0 15px;line-height:1.2;">Ready to Evaluate CCELL® for Your Brand?</h2>
<p style="color:rgba(255,255,255,0.6);font-size:17px;max-width:550px;margin:0 auto 35px;line-height:1.7;">Request complimentary samples and connect with our wholesale team. We will help you find the right hardware for your product line.</p>
<div style="display:flex;gap:15px;justify-content:center;flex-wrap:wrap;">
<a href="/request-samples/" style="background:#c72035;color:#fff;padding:14px 36px;border-radius:4px;font-weight:600;font-size:15px;text-decoration:none;display:inline-block;letter-spacing:0.5px;transition:all 0.3s;">Request Samples</a>
<a href="/contact/" style="background:transparent;color:#fff;padding:14px 36px;border-radius:4px;font-weight:600;font-size:15px;text-decoration:none;display:inline-block;letter-spacing:0.5px;border:1px solid rgba(255,255,255,0.3);">Contact Our Team</a>
</div>
</div>
[/col]
[/row]
[/section]';

$content = str_replace($old_cta, $new_cta, $content);

$stmt = $conn->prepare("UPDATE wp_posts SET post_content=?, post_modified=NOW(), post_modified_gmt=NOW() WHERE ID=96");
$stmt->bind_param('s', $content);
$stmt->execute();

echo "Updated! Affected rows: " . $stmt->affected_rows . "\n";
$conn->close();
