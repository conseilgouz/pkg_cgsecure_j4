<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
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
    ->useScript('form.validate')
    ->useScript('list-view');

$input = Factory::getApplication()->getInput();
$type = $input->get('type', 'ip');

if ($type == 'ht') {
    $filename = Factory::getApplication()->getConfig()->get('log_path').'/cghtaccess.trace.php';
} elseif ($type == 'ip') {
    $filename = Factory::getApplication()->getConfig()->get('log_path').'/cgipcheck.trace.log.php';
} elseif ($type == 'bad') {
    $filename = Factory::getApplication()->getConfig()->get('log_path').'/cgbadrobots.trace.php';
} else { // other log files
    $filename = Factory::getApplication()->getConfig()->get('log_path').'/'.$type;
}
$log = [];
if (file_exists($filename)) {
    $file = fopen($filename, "r");
    fseek($file, 0);

    $bStart = false;
    while (!feof($file)) {
        $line = fgets($file);
        if ((strlen(trim($line)) > 0) && (substr(trim($line), 0, 1) != "#")) {
            $str = str_replace("+00:00", "", $line);
            // cleanup duplicate info in log files
            $tab = explode("\t", $str);
            if ($type == 'ip') {
                $str = $tab[0].'&#9;'.$tab[1].'&#9;'.$tab[3];
            }
            if (sizeof($tab) > 4) { // should not exist, but...
                $str .= '&#9;'.$tab[4];
            }
            $str = str_replace("DEBUG ", "", $str);
            $log[] = "<pre class='cls_log_line' style='tab-size:4'>".$str."</pre>";
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
                    if ($type == 'ht') {
                        $file = 'cghtaccess.trace.php';
                    } elseif ($type == 'ip') {
                        $file = 'cgipcheck.trace.log.php';
                    } else {
                        $file = $type;
                    }
                    echo "<p style='font-size:15px'>".Text::sprintf('COM_CGSECURE_LOGFILE_DESC', $file)."</p>";
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

		
