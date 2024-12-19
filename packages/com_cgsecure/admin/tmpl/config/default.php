<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2024 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

HTMLHelper::_('jquery.framework'); // Force Jquery load
/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

?>

<div class="nr-app nr-app-config">
    <div class="nr-row">
<?php if (!empty($this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
<?php else : ?>
    <div id="j-main-container">
<?php endif;?>
            <div class="nr-main-header">
                <h2><?php echo Text::_('CGSECURE_CONFIG'); ?></h2>
                <p><?php echo Text::_('CGSECURE_CONFIG_DESC'); ?></p>
            </div>
            <div class="nr-main-content">
        		<form action="<?php echo Route::_('index.php?option=com_cgsecure&view=config'); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    		      <div class="form-horizontal">
                    	<?php
                            echo HTMLHelper::_('uitab.startTabSet', 'tab', array('active' => 'basic'));

foreach ($this->form->getFieldSets() as $key => $fieldset) {
    echo HTMLHelper::_('uitab.addTab', 'tab', $fieldset->name, Text::_($fieldset->label));
    echo $this->form->renderFieldSet($fieldset->name);
    echo HTMLHelper::_('uitab.endTab');
}

echo HTMLHelper::_('uitab.endTabSet');
?>
        		    </div>
                    <input id="token" type="hidden" name="<?php echo Session::getFormToken();?>" value="1" />';
        		    <input type="hidden" name="task" value="" />
        		    <input type="hidden" name="name" value="config" />
        		</form>
            </div>
        </div>
    </div>
</div>