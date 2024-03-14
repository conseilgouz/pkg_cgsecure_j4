/**
 * @component     CG Secure
 * Version			: 3.1.1
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2024 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/
var timeout;

jQuery(document).ready(function($){
	$('input[id="jform_htaccess0"]').on("click mouseup keyup",function(){
		$security = $('input[id="jform_security"]').val();
		htaccess($,0,$security); // delete htaccess
		htaccess($,2,$security); // delete hacker IPs lines 
		$('input[id="jform_blockip1"]').removeAttr('checked');
		$('label[for="jform_blockip1"]').removeClass("active btn-success");
		$('input[id="jform_blockip0"]').attr('checked','checked');
		$('label[for="jform_blockip0"]').addClass("active btn-danger");
		$('input[id="jform_security"]').val('0'); // reset security value
	})
	$('input[id="jform_htaccess1"]').on("click mouseup keyup",function(){
		if ($('input[id="jform_security"]').val() == '0') {// security not initialized
			$security = Math.random();                     // create a random value
			$('input[id="jform_security"]').val($security);
		} else { // already exists
			$security = $('input[id="jform_security"]').val();
		}
		htaccess($,1,$security); // create htaccess
	})
	$('input[id="jform_blockip0"]').on("click mouseup keyup",function(){ // delete IPs
		$security = $('input[id="jform_security"]').val();
		htaccess($,2,$security);
	})
	if ($('input[id="jform_multisite"]').val() == "") $('input[id="jform_multisite"]').val(document.URL.split("/")[2]);
});
function htaccess($,access,security) {
			var token = $("#token").attr("name");
            $('#reload').css("display","none")
			$.ajax({
				data: { [token]: "1", task: "display", format: "json", type: "htaccess", access: access, security: security },
				success: function(result, status, xhr) {
					res = result.data.retour;
					$('#cg_result').html(res);
					Joomla.submitbutton('config.apply'); // force save config.
					},
				error: function(message) {console.log(message.responseText)}
			});	
}