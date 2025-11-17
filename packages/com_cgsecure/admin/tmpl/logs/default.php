<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/
//no direct access
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Filesystem\Folder;

$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));

HTMLHelper::_('bootstrap.framework');

$user		= Factory::getApplication()->getIdentity();

/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate')
    ->useScript('list-view');

$wa->registerAndUseScript('securelogs', 'media/com_cgsecure/js/adminlog.js');

$fileht = Factory::getApplication()->getConfig()->get('log_path').'/cghtaccess.trace.php';

$fileip = Factory::getApplication()->getConfig()->get('log_path').'/cgipcheck.trace.log.php';

$filebad = Factory::getApplication()->getConfig()->get('log_path').'/cgbadrobots.trace.php';

$linkViewHTlog = 'index.php?option=com_cgsecure&amp;view=viewlogs&amp;tmpl=component&type=ht';
$linkViewIPlog = 'index.php?option=com_cgsecure&amp;view=viewlogs&amp;tmpl=component&type=ip';
$linkViewBADlog = 'index.php?option=com_cgsecure&amp;view=viewlogs&amp;tmpl=component&type=bad';

$options = Folder::files(Factory::getApplication()->getConfig()->get('log_path'), '.', null, null, [], array('index.html'));

$value = '';
// excluded files cannot be in folder::files as it uses them as wildcard
$exclude = ['cghtaccess.trace.php','cgipcheck.trace.log.php','cgbadrobots.trace.php','.htaccess'];
foreach ($options as $key => $option) {
    if (in_Array($option, $exclude)) {
        unset($options[$key]);
    }
}
$table = Factory::getApplication()->bootComponent('com_cgsecure')->getMVCFactory()->createTable('Config');
$params = json_decode($table->getSecureParams()->params);

?>
<form action="<?php echo Route::_('index.php?option=com_cgsecure&view=logs');?>" method="post" name="adminForm" id="adminForm">
<?php if (!empty($this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
<?php else : ?>
	<div id="j-main-container">
<?php endif;?>
        <div id="logs">
                <!-- Modal !-->
                <?php if (file_exists($fileht)) { ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#viewloght"><?php echo Text::_('COM_CGSECURE_HTLOGS_BUTTON'); ?></button>
                <div class="modal fade modal-xl"  id="viewloght" tabindex="-1" aria-labelledby="loght" aria-hidden="true">
                    <div class="modal-dialog h-75">
                        <div class="modal-content h-100">
                             <div class="modal-header">
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                             </div>
                             <div class="modal-body h-100">
                                <iframe id="iframeModalWindowHT" height="100%" src="<?php echo $linkViewHTlog; ?>" name="iframe_modal_HT"></iframe>      
                             </div>
                        </div>
                    </div>
                </div>
                <?php }
                if (file_exists($fileip)) { ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#viewlogip"><?php echo Text::_('COM_CGSECURE_IPLOGS_BUTTON'); ?></button>
                <div class="modal fade modal-xl"  id="viewlogip" tabindex="-1" aria-labelledby="logip" aria-hidden="true">
                    <div class="modal-dialog h-75">
                        <div class="modal-content h-100">
                             <div class="modal-header">
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                             </div>
                             <div class="modal-body h-100">
                                <iframe id="iframeModalWindowIP" height="100%" src="<?php echo $linkViewIPlog; ?>" name="iframe_modal_IP"></iframe>      
                             </div>
                        </div>
                    </div>
                </div>
                <?php }
                if (file_exists($filebad)) { ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#viewlogbad"><?php echo Text::_('COM_CGSECURE_BADLOGS_BUTTON'); ?></button>
                <div class="modal fade modal-xl"  id="viewlogbad" tabindex="-1" aria-labelledby="logip" aria-hidden="true">
                    <div class="modal-dialog h-75">
                        <div class="modal-content h-100">
                             <div class="modal-header">
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                             </div>
                             <div class="modal-body h-100">
                                <iframe id="iframeModalWindowBAD" height="100%" src="<?php echo $linkViewBADlog; ?>" name="iframe_modal_BAD"></iframe>      
                             </div>
                        </div>
                    </div>
                </div>
                <?php }                ?>

                <div class="modal fade modal-xl"  id="viewlogoth" tabindex="-1" aria-labelledby="othlog" aria-hidden="true">
                    <div class="modal-dialog h-75">
                        <div class="modal-content h-100">
                             <div class="modal-header">
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                             </div>
                             <div class="modal-body h-100">
                                <iframe id="iframeModalWindowOTH" height="100%" src="" name="iframe_modal_OTH"></iframe>      
                             </div>
                        </div>
                    </div>
                </div>
                <!-- Fin de modal !-->
                <?php
                // other log files
                echo Text::_('CGSECURE_OTHER_LOGS');
                echo HTMLHelper::_('select.genericlist', $options, 'adLogs', ' class="adLogs chzn-done" data-chosen="done"', 'element', 'name', $value); 
    ?>

	<div class="filter-search btn-group mb-1 float-end">
		<label for="filter_search" class="element-invisible"><?php echo Text::_('JSEARCH_FILTER_LABEL'); ?></label>  
		<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->escape($this->state->get('filter.search')); ?>" placeholder= "IP Search" title="<?php echo 'IP Search'; ?>" />
        <button type="submit" class="btn btn-primary hasTooltip ms-2"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
		<button type="button" class="btn btn-primary hasTooltip ms-2" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
	</div>
    </div>
	<div style="clear:both"> </div>

    <div class="nr-main-header">
        <h2><?php echo Text::_('CGSECURE_LOGS'); ?></h2>
        <p><?php echo Text::_('CGSECURE_LOGS_DESC'); ?></p>

	<table class="table table-striped">
	<thead>
		<tr>
			<th width="5%">
				<input type="checkbox" name="toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
			</th>			
			<th width="5%" class="center">
				<?php echo HTMLHelper::_('grid.sort', 'COM_CGSECURE_ID', 'a.id', $listDirn, $listOrder); ?>
			</th>
			<th width="15%" class="center">
				<?php echo HTMLHelper::_('grid.sort', 'COM_CGSECURE_IP', 'a.ip', $listDirn, $listOrder); ?>
			</th>
			<th width="10%" class="center">
				<?php echo HTMLHelper::_('grid.sort', 'COM_CGSECURE_COUNTRY', 'a.country', $listDirn, $listOrder); ?>
			</th>
            <th width="20%" class="center">
                <?php echo HTMLHelper::_('grid.sort', 'COM_CGSECURE_LOGS_ACTION', 'a.action', $listDirn, $listOrder); ?>	
            </th>
			<th width="20%" class="center">
				<?php echo HTMLHelper::_('grid.sort', 'COM_CGSECURE_LOGS_ATTEMPTDATE', 'a.attempt_date', $listDirn, $listOrder); ?>
			</th>
			

		</tr>			
	</thead>
	<?php
    $k = 0;
$n = count($this->items);
for ($i = 0; $i < $n; $i++) {
    $item = $this->items[$i];
    $checked 	= HTMLHelper::_('grid.id', $i, $item->id);
    $canEdit	= $user->authorise('core.edit', 'com_cgsecure');

    ?>
		<tr class="row<?php echo $i % 2; ?>">
			<td class="center">
				<?php echo $checked; ?>
			</td>
			<td>
				<?php echo $this->escape($item->id); ?>
			</td>
			<td class="center">
				<?php echo $this->escape($item->ip); ?>
			</td>
			<td class="center">
				<?php echo $this->escape($item->country); ?>
			</td>
			<td class="center">
                <?php echo $this->escape($item->action);?>
            </td>
			<td class="center">
				<?php echo HTMLHelper::_('date', $item->attempt_date, 'Y-m-d H:i:s'); ?>
			</td>
		</tr>
		<?php
    $k = 1 - $k;
}
?>
    <tfoot>
    <tr>
      <td colspan="11"><?php echo $this->pagination->getListFooter(); ?></td>
    </tr>
  	</tfoot>
	</table>
<div>
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
    <input type="hidden" name="security"  value="<?php echo $params->security; ?>"/>
	<input type="hidden" id="logshtaccess" value="<?php echo $params->htaccess;?>" />
    <input type="hidden" id="logsblockip" value="<?php echo $params->blockip;?>" />
	<?php echo HTMLHelper::_('form.token'); ?>
</div>
</div>
</form>