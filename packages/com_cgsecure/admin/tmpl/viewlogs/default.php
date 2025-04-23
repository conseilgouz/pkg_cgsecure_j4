<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/
// no direct access
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$filename = Factory::getApplication()->getConfig()->get('log_path').'/cghtaccess.trace.php';
$log = [];
if (file_exists($filename)) {
    $file = fopen($filename, "r");
    fseek($file, 0);

    $bStart = false;
    while (!feof($file)) {
        $line = fgets($file);
        if ((strlen(trim($line)) > 0) && (substr(trim($line), 0, 1) != "#")) {
            $str = str_replace("+00:00", "", $line);
            $str = str_replace("DEBUG", "", $str);
            $tbl = explode(' ', $str, 3);
            $elem = preg_split('/\s+/', $tbl[1]);
            $str = '<span class="log_date col-2">'.$tbl[0].'</span>';
            $str .= '<span class="log_ip col-2"> ('.$elem[0].')</span>';
            $str .= '<span class="log_module col-1">'.$elem[1].'</span> ';
            $str .= '<span class="log_error col-7">'.$tbl[2].'</span>';
            $log[] = "<div class='cls_log_line row'>".$str."</div>";
        }
    }
    fclose($file);
    $log = array_reverse($log);
}
?>
<form action="<?php echo Route::_('index.php?option=com_cgsecure&view=viewlogs'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">
	<div id="j-log-container">
       <h2><?php echo Text::sprintf('COM_CGSECURE_LOGFILE', $filename);?></h2>
	   <div class="cls_log">	
			<?php
                if (count($log) > 0) {
                    echo "<p style='font-size:15px'>".Text::_('COM_CGSECURE_VIEWLOG_DESC')."</p>";
                    for ($i = 0;$i < count($log);$i++) {
                        echo $log[$i];
                    }
                } else {
                    echo "<p style='font-size:15px'>Pas de fichier log disponible</p>";
                }
?>
	   </div>
	</div>
    <div class="clearfix"> </div>
        <?php echo HTMLHelper::_('form.token'); ?>
	</div>
</form>        

		
