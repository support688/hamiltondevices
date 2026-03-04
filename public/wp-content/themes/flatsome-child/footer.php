<?php
/**
 * The template for displaying the footer.
 *
 * @package          Flatsome\Templates
 * @flatsome-version 3.16.0
 */

global $flatsome_opt;
?>

</main>

<footer id="footer" class="footer-wrapper">

	<?php do_action('flatsome_footer'); ?>

</footer>

</div>

<?php wp_footer(); ?>

<script>

let copybtn = document.querySelector(".copybtn");
let copybtn1 = document.querySelector(".copybtn1");
let copybtn2 = document.querySelector(".copybtn2");
let copybtn3 = document.querySelector(".copybtn3");


function copyIt(){
  let copyInput = document.querySelector('#copyvalue');

  copyInput.select();

  document.execCommand("copy");

  copybtn.textContent = "COPIED";
}
	

	function copyIt1(){
  let copyInput1 = document.querySelector('#copyvalue1');

  copyInput1.select();

  document.execCommand("copy");

  copybtn1.textContent = "COPIED";
}	function copyIt2(){
  let copyInput2 = document.querySelector('#copyvalue2');

  copyInput2.select();

  document.execCommand("copy");

  copybtn2.textContent = "COPIED";
}
function copyIt3(){
  let copyInput3 = document.querySelector('#copyvalue3');

  copyInput3.select();

  document.execCommand("copy");

  copybtn3.textContent = "COPIED";
}

</script>
<!-- Start of LiveChat (www.livechat.com) code -->
<script>
    window.__lc = window.__lc || {};
    window.__lc.license = 15512769;
    ;(function(n,t,c){function i(n){return e._h?e._h.apply(null,n):e._q.push(n)}var e={_q:[],_h:null,_v:"2.0",on:function(){i(["on",c.call(arguments)])},once:function(){i(["once",c.call(arguments)])},off:function(){i(["off",c.call(arguments)])},get:function(){if(!e._h)throw new Error("[LiveChatWidget] You can't use getters before load.");return i(["get",c.call(arguments)])},call:function(){i(["call",c.call(arguments)])},init:function(){var n=t.createElement("script");n.async=!0,n.type="text/javascript",n.src="https://cdn.livechatinc.com/tracking.js",t.head.appendChild(n)}};!n.__lc.asyncInit&&e.init(),n.LiveChatWidget=n.LiveChatWidget||e}(window,document,[].slice))
</script>
<noscript><a href="https://www.livechat.com/chat-with/15512769/" rel="nofollow">Chat with us</a>, powered by <a href="https://www.livechat.com/?welcome" rel="noopener nofollow" target="_blank">LiveChat</a></noscript>
<!-- End of LiveChat code -->
</body>
</html>

