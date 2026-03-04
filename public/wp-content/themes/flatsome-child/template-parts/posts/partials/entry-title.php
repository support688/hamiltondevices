<?php
/**
 * Post-entry title.
 *
 * @package          Flatsome\Templates
 * @flatsome-version 3.16.0
 */

?>
<h6 class="entry-category is-xsmall">
	<?php echo get_the_category_list( __( ', ', 'flatsome' ) ) ?>
</h6>

<?php
if ( is_single() ) {
	echo '<h1 class="entry-title">' . get_the_title() . '</h1>';
} else {
	echo '<h2 class="entry-title"><a href="' . get_the_permalink() . '" rel="bookmark" class="plain">' . get_the_title() . '</a></h2>';
}
?>

<?php 
 $time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
    if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
        $time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
    }

    $time_string = sprintf( $time_string,
        esc_attr( get_the_date( 'c' ) ),
        esc_html( get_the_date() ),
        esc_attr( get_the_modified_date( 'c' ) ),
        esc_html( get_the_modified_date() )
    );
	?>
    
<h3 class="fgsfdtydf"><?php echo the_field('text_aretext'); ?></h3>
<div class="rfhgdsfhdifkyh"><div class="dfdftret"><?php $tags = get_tags(); ?>
<div class="tags">
<?php foreach ( $tags as $tag ) { ?>
    <a href="<?php echo get_tag_link( $tag->term_id ); ?> " rel="tag"><?php echo $tag->name; ?></a>
<?php } ?>
</div></div>
<span class="meta-author ftjfg"><a><?php echo $time_string ?>      </a></span>
<span class="meta-author vcard">   By <a class="url fn n"> <?php echo( get_the_author() )?>  </a></span>
<div class="ddserfdftret"><?php if ( get_theme_mod( 'blog_share', 1 ) ) {
		// SHARE ICONS
		echo '<div class="blog-sharessf text-center">';
		echo do_shortcode( '[share]' );
		echo '</div>';
	} ?></div></div>
<div class="entry-divider is-divider small"></div>

<?php
$single_post = is_singular( 'post' );
if ( $single_post && get_theme_mod( 'blog_single_header_meta', 1 ) ) : ?>
	<div class="entry-meta uppercase is-xsmall">
		<?php flatsome_posted_on(); ?>
	</div><!-- .entry-meta -->
<?php elseif ( ! $single_post && 'post' == get_post_type() ) : ?>
	<div class="entry-meta uppercase is-xsmall">
		<?php flatsome_posted_on(); ?>
	</div><!-- .entry-meta -->
<?php endif; ?>