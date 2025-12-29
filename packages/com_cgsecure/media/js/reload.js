/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/
var timeout;

document.addEventListener('DOMContentLoaded', function(){
    let reload = document.querySelector('#reload');
    let lhtaccess = document.querySelector('#logshtaccess');
    let lblockip = document.querySelector('#logsblockip');
    if (lhtaccess) { // reload in logs page
        if (lhtaccess.value == '0') {
            reload.style.display = 'none';
        }
        if (lblockip && lblockip.value == '0') {
            reload.style.display = 'none';
        }
    }
    reload.addEventListener('click',function(){
        security = document.querySelector('input[id="jform_security"]');
		if (!security || (security.value == '0')) {// security not initialized
			$security = Math.random();                     // create a random value
            if (security) {
                security.value = $security;
            }
		} else { // already exists
			$security = security.value;
		}
        $access = 0;
        access = document.querySelector('input[id="jform_htaccess1"]');
        if (!access) $access = 1; // from logs page
        else if (access && hasClass(access,'active')) $access = 1; 
		create_htaccess($access,$security); // create htaccess
	})
});
function create_htaccess(access,security) {
	var token = document.querySelector("#token");
    if (!token) { // from logs page
        token = Joomla.getOptions("csrf.token", "");
    } else {
        token = token.getAttribute("name");
    }
    url = '?'+token+'=1&option=com_cgsecure&task=display&type=htaccess&access='+access+'&security='+security+'&format=json';
	Joomla.request({
		method : 'POST',
		url : url,
		onSuccess: function(data, xhr) {
            var result = JSON.parse(data);
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
hasClass = function (el, cl) {
    var regex = new RegExp('(?:\\s|^)' + cl + '(?:\\s|$)');
    return !!el.className.match(regex);
}

