<?php
/*
Template Name: wholesale approval :)
*/

get_header();
?>
<style>
    /* Popup box BEGIN */
.hover_bkgr_fricc{
    background:rgba(0,0,0,.4);
    cursor:pointer;
    display:none;
    height:100%;
    position:fixed;
    text-align:center;
    top:0;
    width:100%;
    z-index:10000;
}
.hover_bkgr_fricc .helper{
    display:inline-block;
    height:100%;
    vertical-align:middle;
}
.hover_bkgr_fricc > div {
    background-color: #fff;
    box-shadow: 10px 10px 60px #555;
    display: inline-block;
    height: auto;
    max-width: 551px;
    min-height: 100px;
    vertical-align: middle;
    width: 60%;
    position: relative;
    border-radius: 8px;
    padding: 15px 5%;
}
.popupCloseButton {
    background-color: #fff;
    border: 3px solid #999;
    border-radius: 50px;
    cursor: pointer;
    display: inline-block;
    font-family: arial;
    font-weight: bold;
    position: absolute;
    top: -20px;
    right: -20px;
    font-size: 25px;
    line-height: 30px;
    width: 30px;
    height: 30px;
    text-align: center;
}.appro {
    background: green;
}.rej {
    background: #E50914;
}
.popupCloseButton:hover {
    background-color: #ccc;
}
.trigger_popup_fricc {
    cursor: pointer;
    font-size: 20px;
    margin: 20px;
    display: inline-block;
    font-weight: bold;
}
</style>
<?php
 do_action( 'flatsome_before_page' ); $current_user = wp_get_current_user();

?>
<div class="hover_bkgr_fricc">
    <span class="helper"></span>
    <div>
        
        <p>Processing.....</p>
    </div>
</div>
<div class="row page-wrapper">
<div id="content" class="large-12 col" role="main">
    <?php 
if (user_can( $current_user, 'administrator' )) {
  // user is an admin

$args1 = array(
   
    'orderby' => 'user_nicename',
    'order'   => 'ASC'
);
$users1 = get_users( $args1 );

/* 
$args2 = array(
    'role'    => 'wwlc_unapproved',
    'orderby' => 'user_nicename',
    'order'   => 'ASC'
);
$users2 = get_users( $args2 );
*/
?>
<table>
<tr><td colspan='7' style='text-align:center'><b> Wholesale customers</b></td></tr>
<tr><td><b>Email</b></td><td><b>Company</b></td><td><b>Phone</b></td><td><b>License</b></td><td><b>Seller's Permit	</b></td><td><b>TAX EIN	</b></td><td><b>Manage</b></td></tr><?php
$i=1;
foreach ( $users1 as $user ) {
     
  $i++;
  $user_meta=get_userdata($user->ID);

if(in_array('wholesale_customer', $user_meta->roles) || in_array('wwlc_unapproved', $user_meta->roles)) {
   
     
 ?><tr><td>  <?php echo esc_html( $user->user_email ) ; ?></td><td><?php echo esc_html( $user->wwlc_company_name); ?></td><td> <?php  echo esc_html( $user->wwlc_phone ); ?></td><td> <?php if (esc_html( $user->wwlc_cf_dc )) { ?><a target='_blank' href='<?php echo esc_html( $user->wwlc_cf_dc ); ?>'>view file</a><?php }?></td><td> <?php if (esc_html( $user->wwlc_cf_slp )) { ?><a target='_blank' href='<?php echo esc_html( $user->wwlc_cf_slp ); ?>'>view file</a><?php }?></td><td> <?php if (esc_html( $user->wwlc_cf_txin )) { ?><a target='_blank' href='<?php echo esc_html( $user->wwlc_cf_txin ); ?>'>view file</a><?php }?></td><td><?php if(in_array('wholesale_customer', $user_meta->roles)) { ?> <button class="rej" id='rej<?php echo $i; ?>' type='button' onclick='updaterole("<?php echo $user->ID ?>","reject","<?php echo $i ?>")'>Reject</button> <button class="appro" id='apbtn<?php echo $i; ?>' type='button' style='display:none;' onclick='updaterole("<?php echo $user->ID ?>","approve","<?php echo $i ?>")'>Approve</button><?php } else { ?><button id='apbtn<?php echo $i; ?>' type='button' class="appro" onclick='updaterole("<?php echo $user->ID ?>","approve","<?php echo $i ?>")'>Approve</button><button type='button' class="rej" style='display:none' id='rej<?php echo $i; ?>' onclick='updaterole("<?php echo $user->ID ?>","reject","<?php echo $i ?>")'>Reject</button>  <?php } ?></td></tr><?php 
}
}
?></table>
<?php do_action( 'flatsome_after_page' ); } else {
    echo "You dont have permission to access this page";
} ?>
</div><!-- #content -->
</div><!-- .row -->

<?php 
 get_footer();?>


<script>
    function updaterole(id,action,i) {
        jQuery('.hover_bkgr_fricc div p').html("Processing...");
jQuery('.hover_bkgr_fricc').show();
    
   

    
    jQuery.ajax({
        url: '../update_role.php', /* <====== missing here */
        type : 'post', /*    <========== and missing here */
        data: {
            'action': action,
            'userid' : id
        },
        success:function(data) {
          
            if(data == 'approved') {
               
                jQuery('.hover_bkgr_fricc div p').html("Approved");
               jQuery("#rej"+i).css("display", "block");
               jQuery("#apbtn"+i).css("display", "none");
            } else {
              jQuery('.hover_bkgr_fricc div p').html("Rejected");
                jQuery("#rej"+i).css("display", "none");
                
               jQuery("#apbtn"+i).css("display", "block");
            }
                setTimeout(function() {
                   jQuery('.hover_bkgr_fricc').fadeOut('fast');
                    
                }, 2000);
                
            console.log(data);
        },
        error: function(errorThrown){
            console.log(errorThrown);
        }
    });  

}
</script>