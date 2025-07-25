/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/
var timeout;
document.addEventListener('DOMContentLoaded', function(){
    bad0 = document.querySelector('input[id="jform_blockbad0"]');
    bad1 = document.querySelector('input[id="jform_blockbad1"]');
    
    ['click', 'mouseup', 'touchstart'].forEach(type => {    
        bad0.addEventListener(type,function(){
            badrobots(0,0); // delete badrobots check 
        })
        bad1.addEventListener(type,function(){
            badrobots(1,0); // create badrobots block
        })
    })
});
function badrobots(access,security) {
    if (cgsecureinprogress) return;
    cgsecureinprogress = true;
    var appconfig = document.querySelector('.nr-app-config');
    var cgmodal = document.getElementById('cgsecure_modal');
    if (cgmodal) {
        cgmodal.classList.add('show');
        cgmodal.style.display = 'block';
        appconfig.style.pointerEvents = "none";
    }
	var token = document.querySelector("#token").getAttribute("name");
    url = '?'+token+'=1&option=com_cgsecure&task=display&type=robots&access='+access+'&security='+security+'&format=json';
	Joomla.request({
		method : 'POST',
		url : url,
		onSuccess: function(data, xhr) {
            var result = JSON.parse(data);
            res = result.data.retour;
            console.log('res : '+res);
            bad = document.querySelector('#result_bad_wd');
            bad.innerHTML = res;
            cgsecureinprogress = false;
            if (cgmodal) {
                cgmodal.classList.remove('show');
                cgmodal.style.display = 'none';
                appconfig.style.pointerEvents = "all";
            }
            
            // Joomla.submitbutton('config.apply'); // force save config.
		},
		error: function(message) {
            console.log(message.responseText);
            cgsecureinprogress = false;
            if (cgmodal) cgmodal.classList.remove('show');
            appconfig.style.pointerEvents = "all";
        }
	});	
}
