/**
 * @component     CG Secure
 * Version			: 2.1.5
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @copyright (C) 2022 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/
var timeout;

jQuery(document).ready(function($){
	$('input[id="jform_blockbad0"]').on("click mouseup keyup",function(){
		badrobots($,0,0); // delete badrobots check 
	})
	$('input[id="jform_blockbad1"]').on("click mouseup keyup",function(){
		badrobots($,1,0); // create badrobots block
	})
});
function badrobots($,access,security) {
	var token = $("#token").attr("name");
	$.ajax({
		data: { [token]: "1", task: "display", format: "json",type:"robots", access: access, security: security },
		success: function(result, status, xhr) {
		res = result.data.retour;
		$('#result_bad_wd').html(res);
			Joomla.submitbutton('config.apply'); // force save config.
		},
		error: function(message) {console.log(message.responseText)}
	});	
}