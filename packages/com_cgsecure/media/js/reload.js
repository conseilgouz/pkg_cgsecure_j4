/**
 * @component     CG Secure
 * Version			: 2.1.5
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @copyright (C) 2022 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/
var timeout;

jQuery(document).ready(function($){
	if (!$('label[for="jform_htaccess1"]').hasClass('active btn-success')) {
		// $('#reload').css("display","none")
	}
	$('#reload').on("click",function(){
		if ($('input[id="jform_security"]').val() == '0') {// security not initialized
			$security = Math.random();                     // create a random value
			$('input[id="jform_security"]').val($security);
		} else { // already exists
			$security = $('input[id="jform_security"]').val();
		}
		create_htaccess($,1,$security); // create htaccess
	})
});
function create_htaccess($,access,security) {
			var token = $("#token").attr("name");
			$.ajax({
				data: { [token]: "1", task: "display", format: "json",type: "htaccess", access: access, security: security },
				success: function(result, status, xhr) {
					res = result.data.retour;
					$('#cg_result').html(res);
					Joomla.submitbutton('config.apply'); // force save config.
					},
				error: function(message) {console.log(message.responseText)}
			});	
}