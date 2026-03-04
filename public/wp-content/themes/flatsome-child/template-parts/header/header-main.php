<?php
/**
 * Header main.
 *
 * @package          Flatsome\Templates
 * @flatsome-version 3.16.0
 */

?>
<div id="masthead" class="header-main <?php header_inner_class('main'); ?>">
      <div class="header-inner flex-row container <?php flatsome_logo_position(); ?>" role="navigation">

          <!-- Logo -->
          <div id="logo" class="flex-col logo" style="display:flex;align-items:center;gap:0;">
              <a class="cel" href="/product-category/ccell/" title="CCELL Products | Hamilton Devices" rel="home" style="width:170px;margin-right:14px;flex-shrink:0;"><img src="/wp-content/uploads/2025/12/ccellnewlogo.png" class="header-logo" alt="CCELL" style="width:100%;height:auto;display:block !important;"></a>
              <a class="ccell" href="/" title="Hamilton Devices" rel="home" style="flex-shrink:0;"><img src="/wp-content/uploads/2022/11/hamilton.png" class="header-logo" alt="Hamilton Devices" style="height:90px;width:auto;display:block !important;"></a>
          </div>

          <!-- Mobile Left Elements -->
          <div class="flex-col show-for-medium flex-left">
            <ul class="mobile-nav nav nav-left <?php flatsome_nav_classes('main-mobile'); ?>">
              <?php flatsome_header_elements('header_mobile_elements_left','mobile'); ?>
              
            </ul>
            
          </div>

          <!-- Left Elements -->
          <div class="flex-col hide-for-medium flex-left
            <?php if(get_theme_mod('logo_position', 'left') == 'left') echo 'flex-grow'; ?>">
            <ul class="header-nav header-nav-main nav nav-left <?php flatsome_nav_classes('main'); ?>" >
              <?php flatsome_header_elements('header_elements_left'); ?>
            </ul>
          </div>

          <!-- Right Elements -->
          <div class="flex-col hide-for-medium flex-right">
            <ul class="header-nav header-nav-main nav nav-right <?php flatsome_nav_classes('main'); ?>">
              <?php flatsome_header_elements('header_elements_right'); ?>
            </ul>
          </div>

          <!-- Mobile Right Elements -->
          <div class="flex-col show-for-medium flex-right">
            <ul class="mobile-nav nav nav-right <?php flatsome_nav_classes('main-mobile'); ?>">
                 <button  onclick='displaysearch()' class="mobsearch">
					<i class="icon-search"></i>				</button>
              <?php flatsome_header_elements('header_mobile_elements_right','mobile'); ?>
             
            </ul>
          </div>
           

      </div><!-- .header-inner -->
     <div class="flex-col show-for-medium flex-bottom">
            <ul  class="mobile-nav nav nav-right <?php flatsome_nav_classes('main-mobile'); ?>">
<li id="mobviewsearch" class="header-search-form search-form html relative has-icon">
	<div class="header-search-form-wrapper">
		<?php echo do_shortcode('[search style="'.flatsome_option('header_search_form_style').'"]'); ?>
	</div>
</li>            </ul>
          </div>
      <?php if(get_theme_mod('header_divider', 1)) { ?>
      <!-- Header divider -->
      <div class="container"><div class="top-divider full-width"></div></div>
      <?php }?>
</div><!-- .header-main -->
<script>
    function displaysearch(){
        if(document.getElementById("mobviewsearch").style.display=='none')
{
    
document.getElementById("mobviewsearch").style.display="block";
}
else
{
    
document.getElementById("mobviewsearch").style.display="none";
}
    }
</script>