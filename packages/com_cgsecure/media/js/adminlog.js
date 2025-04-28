/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/
var timeout;

document.addEventListener('DOMContentLoaded', function(){
    logs = document.querySelector('#adLogs');
    logs.addEventListener('change', function (ev) {
		var csrf = Joomla.getOptions("csrf.token", "");
		var url = "?"+csrf+"=1&option=com_cgsecure&tmpl=component&adLogs="+ev.srcElement.selectedOptions[0].text+"&type=logs&format=json";
		Joomla.request({
			method : 'POST',
			url : url,
			onSuccess: function(data, xhr) {
                response = JSON.parse(data);
                console.log(JSON.parse(data));
                addr =  response.data.retour;
                modal = document.querySelector('#iframeModalWindowOTH');
                modal.src = addr;
                modal.style.display = 'block';
                modal = new bootstrap.Modal(document.getElementById('viewlogoth'));
                modal.show();
			},
			onError: function(message) {console.log(message.responseText)}
		}) 
        
	})
});

