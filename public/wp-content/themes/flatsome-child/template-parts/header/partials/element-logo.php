<?php
/**
 * Logo element.
 *
 * @package          Flatsome\Templates
 * @flatsome-version 3.16.0
 */

$site_logo_id        = flatsome_option( 'site_logo' );
$site_logo_sticky_id = flatsome_option( 'site_logo_sticky' );
$site_logo_dark_id   = flatsome_option( 'site_logo_dark' );
$site_logo           = wp_get_attachment_image_src( $site_logo_id, 'large' );
$site_logo_sticky    = wp_get_attachment_image_src( $site_logo_sticky_id, 'large' );
$site_logo_dark      = wp_get_attachment_image_src( $site_logo_dark_id, 'large' );
$logo_link           = get_theme_mod( 'logo_link' );
$logo_link           = $logo_link ? $logo_link : home_url( '/' );
$width               = get_theme_mod( 'logo_width', 200 );
$height              = get_theme_mod( 'header_height', 90 );

if ( ! empty( $site_logo_id ) && ! is_numeric( $site_logo_id ) ) {
	// Fallback to `logo_width` and `header_height` if
	// the logo is a string, ie. it's the default value.
	$site_logo = array( $site_logo_id, $width, $height );
}

if ( ! empty( $site_logo_sticky_id ) && ! is_numeric( $site_logo_sticky_id ) ) {
	$site_logo_sticky = array( $site_logo_sticky_id, $width, $height );
}

if ( ! empty( $site_logo_dark_id ) && ! is_numeric( $site_logo_dark_id ) ) {
	$site_logo_dark = array( $site_logo_dark_id, $width, $height );
}

?>

<!-- Header logo -->
<a href="https://hamiltondevices.com/product-category/ccell/" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?><?php echo get_bloginfo( 'name' ) && get_bloginfo( 'description' ) ? ' - ' : ''; ?><?php bloginfo( 'description' ); ?>" rel="home">
    <?php if(flatsome_option('site_logo')){
      $logo_height = get_theme_mod('header_height',90);
      $logo_width = get_theme_mod('logo_width', 200);
      $site_title = esc_attr( get_bloginfo( 'name', 'display' ) );
      if(get_theme_mod('site_logo_sticky')) echo '<img width="'.$logo_width.'" height="'.$logo_height.'" src="'.get_theme_mod('site_logo_sticky').'" class="header-logo-sticky" alt="'.$site_title.'"/>';
      echo '<img width="'.$logo_width.'" height="'.$logo_height.'" src="'.flatsome_option('site_logo').'" class="header_logo header-logo" alt="'.$site_title.'"/>';
      if(!get_theme_mod('site_logo_dark')) echo '<img  width="'.$logo_width.'" height="'.$logo_height.'" src="'.flatsome_option('site_logo').'" class="header-logo-dark" alt="'.$site_title.'"/>';
      if(get_theme_mod('site_logo_dark')) echo '<img  width="'.$logo_width.'" height="'.$logo_height.'" src="'.get_theme_mod('site_logo_dark').'" class="header-logo-dark" alt="'.$site_title.'"/>';
    } else {
    bloginfo( 'name' );
  	}
  ?>
</a>
<?php
if(get_theme_mod('site_logo_slogan')){
	echo '<p class="logo-tagline">'.get_bloginfo('description').'</p>';
}
?>
