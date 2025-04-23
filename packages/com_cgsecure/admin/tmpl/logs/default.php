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

$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));

HTMLHelper::_('bootstrap.framework');

$user		= Factory::getApplication()->getIdentity();

/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');
    
$linkViewlog = 'index.php?option=com_cgsecure&amp;view=viewlogs&amp;tmpl=component';
    
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
                <!-- Modal !-->
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#viewlog"><?php echo Text::_('COM_CGSECURE_LOGS_BUTTON'); ?></button>
                <div class="modal fade modal-xl"  id="viewlog" tabindex="-1" aria-labelledby="upload" aria-hidden="true">
                    <div class="modal-dialog h-75">
                        <div class="modal-content h-100">
                             <div class="modal-header">
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                             </div>
                             <div class="modal-body h-100">
                                <iframe id="iframeModalWindow" height="100%" src="<?php echo $linkViewlog; ?>" name="iframe_modal"></iframe>      
                             </div>
                        </div>
                    </div>
                </div>
                <!-- Fin de modal !-->



    <div class="nr-main-header">
        <h2><?php echo Text::_('CGSECURE_LOGS'); ?></h2>
        <p><?php echo Text::_('CGSECURE_LOGS_DESC'); ?></p>
    </div>

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
	<?php echo HTMLHelper::_('form.token'); ?>
</div>
</div>
</form>