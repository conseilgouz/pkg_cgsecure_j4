/**
 * @component     CG Secure
 * Version			: 2.1.5
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @copyright (C) 2022 ConseilGouz. All Rights Reserved.
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
