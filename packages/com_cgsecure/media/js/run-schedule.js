/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/
const initCGScheduler = () => {
  const interval = 300 * 1000;
  const uri = cgbase+`index.php?option=com_ajax&format=raw&plugin=RunSchedulerLazy&group=system`;
  setInterval(() => fetch(uri, {
    method: 'GET'
  }), interval);

  // Run it at the beginning at least once
  fetch(uri, {
    method: 'GET'
  });
};
(document => {
  document.addEventListener('DOMContentLoaded', initCGScheduler);
})(document);
