/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
 *
 * from https://stackoverflow.com/questions/391979/how-to-get-clients-ip-address-using-javascript
**/
 function getIP(data) {
	document.addEventListener('DOMContentLoaded', function(){
		$result = document.getElementById("result");
		$html = $result.innerHTML;
		$ip = data['ip'];
		$result.innerHTML = $html+$ip;
		$html = document.getElementById("jform_whitelist").value;
		if ($html.trim() == '') document.getElementById("jform_whitelist").value = $ip;
		$html = document.getElementById("jform_country").value;
		$lang = navigator.language;
		if ($lang.length != 2) {
			$lang = $lang.substr($lang.length-2,2);
		}
		if ($html.trim() == '') document.getElementById("jform_country").value = $lang;
	});
}
// check IP address 
document.addEventListener('DOMContentLoaded', function(){
    check = document.querySelector('#jform_checkip0'); 
    ['click', 'mouseup', 'touchstart'].forEach(type => {
        check.addEventListener(type, function(){
            checkip();
        })
    })
})
function checkip() {
	var token = document.querySelector("#token").getAttribute("name");
    ip = document.querySelector('#jform_ip').value;
    var url = "?"+token+"=1&option=com_cgsecure&tmpl=component&view=ip&type=check&ip="+ip+"&format=json";
	Joomla.request({
		method : 'POST',
		url : url,
		onSuccess: function(data, xhr) {
            var result = JSON.parse(data);
            if (result.data.error) {
               res = result.data.error;
            } else {
                res = result.data.retour;
            }
            document.querySelector('#result').innerHTML = res;
            document.querySelector('#result').value = res;
            document.querySelector('#result').style.display = 'block';
            document.querySelector('#result').style.width = '50em';
		},
		error: function(message) {console.log(message.responseText);}
	});	
}
