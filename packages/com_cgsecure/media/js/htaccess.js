/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/
var cgsecureinprogress;

document.addEventListener('DOMContentLoaded', function(){
    htaccess0 = document.querySelector('input[id="jform_htaccess0"]');
    htaccess1 = document.querySelector('input[id="jform_htaccess1"]');
    blockip0 = document.querySelector('input[id="jform_blockip0"]');
    blockip1 = document.querySelector('input[id="jform_blockip1"]');
    blockipv60 = document.querySelector('input[id="jform_blockipv60"]');
    blockipv61 = document.querySelector('input[id="jform_blockipv61"]');
    blockai0 = document.querySelector('input[id="jform_blockai0"]');
    blockai1 = document.querySelector('input[id="jform_blockai1"]');
    blockhotlink0 = document.querySelector('input[id="jform_blockhotlink0"]');
    blockhotlink1 = document.querySelector('input[id="jform_blockhotlink1"]');
    ['click', 'mouseup', 'touchstart'].forEach(type => {    
        htaccess0.addEventListener(type, function(){
            security = document.querySelector('input[id="jform_security"]').value;
            htaccess(0,security); // delete htaccess
            htaccess(2,security); // delete hacker IPs lines 
            block1 = document.querySelector('input[id="jform_blockip1"]');
            block1.removeAttribute('checked');
            lbl = document.querySelector('label[for="jform_blockip1"]');
            removeClass(lbl,"active");
            removeClass(lbl,"btn-success");
            block0 = document.querySelector('input[id="jform_blockip0"]');
            block0.setAttribute('checked','checked');
            lbl = document.querySelector('label[for="jform_blockip0"]');
            lbl.classList.add("active");
            lbl.classList.add("btn-danger");
            security = document.querySelector('input[id="jform_security"]');
            security.value = '0'; // reset security value
        })
        htaccess1.addEventListener(type,function(){
            security = document.querySelector('input[id="jform_security"]');
            if (security.value == '0') {// security not initialized
                $security = Math.random();                     // create a random value
                security.value = $security;
            } else { // already exists
                $security = $security.value;
            }
            htaccess(1,$security); // create htaccess
        })
        blockip0.addEventListener(type,function(){ // delete IPs
            security = document.querySelector('input[id="jform_security"]');
            htaccess(2,security.value);
        })
        blockip1.addEventListener(type,function(){ // add IPs
            security = document.querySelector('input[id="jform_security"]');
            htaccess(5,security.value);
        })
        blockipv60.addEventListener(type,function(){ // delete IPs V6
            security = document.querySelector('input[id="jform_security"]');
            htaccess(6,security.value);
        })
        blockipv61.addEventListener(type,function(){ // add IPs V6
            security = document.querySelector('input[id="jform_security"]');
            htaccess(7,security.value);
        })
        blockai0.addEventListener(type,function(){ // delete AI Bots
            security = document.querySelector('input[id="jform_security"]');
            htaccess(3,security.value);
        })
        blockai1.addEventListener(type,function(){ // add  AI Bots
            security = document.querySelector('input[id="jform_security"]');
            htaccess(4,security.value);
        })
        blockhotlink0.addEventListener(type,function(){ // delete hotlink block
            security = document.querySelector('input[id="jform_security"]');
            htaccess(8,security.value);
        })
        blockhotlink2.addEventListener(type,function(){ // add  hotlink block
            security = document.querySelector('input[id="jform_security"]');
            htaccess(9,security.value);
        })
	})
    multi = document.querySelector('input[id="jform_multisite"]')
	if (multi.value == "") multi.value = document.URL.split("/")[2];
});
function htaccess(access,security) {
	var token = document.querySelector("#token").getAttribute("name");
    if (cgsecureinprogress) return;
    reload = document.querySelector('#reload');
    reload.style.display = "none";
    cgsecureinprogress = true;
    url = '?'+token+'=1&option=com_cgsecure&task=display&type=htaccess&access='+access+'&security='+security+'&format=json';
	Joomla.request({
		method : 'POST',
		url : url,
		onSuccess: function(data, xhr) {
            var result = JSON.parse(data);
            res = result.data.retour;
            console.log('res : '+res);
            document.querySelector('#result_wd').innerHTML = res;
            Joomla.submitbutton('config.apply'); // force save config.
		},
		error: function(message) {console.log(message.responseText);cgsecureinprogress = false;}
	});	
}
removeClass = function (el, cl) {
    var regex = new RegExp('(?:\\s|^)' + cl + '(?:\\s|$)');
    el.className = el.className.replace(regex, ' ');
}
