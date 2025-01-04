/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
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
        $access = 0;
        if ($('input[id="jform_htaccess1"]').hasClass('active')) $access= 1;
		create_htaccess($,$access,$security); // create htaccess
	})
});
function create_htaccess($,access,security) {
	var token = $("#token").attr("name");
	$.ajax({
		data: { [token]: "1", task: "display", format: "json",type: "htaccess", access: access, security: security },
		success: function(result, status, xhr) {
            res = result.data.retour;
            console.log('res : '+res);
            if (res.startsWith('err :')) {
               Joomla.renderMessages({error: [res]});
               return; // contains an error : exit
            }
            Joomla.submitbutton('config.apply'); // force save config.
		},
		error: function(message) {console.log(message.responseText)}
	});	
}
