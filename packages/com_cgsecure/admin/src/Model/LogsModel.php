<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2024 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\Model;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseInterface;

/**
 * Config Model Class
 */
class LogsModel extends ListModel
{
    public function __construct($config = array(), ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
            'id', 'a.id',
            'action', 'a.action',
            'country', 'a.country',
            'ip', 'a.ip',
            'errtype', 'a.errtype',
            'attempt_date', 'a.attempt_date',
            );
        }

        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        $search = $this->getUserStateFromRequest($this->context.'.filter.search', 'filter_search');
        $this->setState('filter.search', $search);
        parent::populateState('a.id', 'desc');
    }

    protected function getStoreId($id = '')
    {
        $id	.= ':'.$this->getState('filter.search');

        return parent::getStoreId($id);
    }

    protected function getListQuery()
    {
        // Create a new query object.
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query	= $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                'a.*'
            )
        );
        $tn = "#__cg_rejected_ip";
        $query->from($db->quoteName($tn) . ' AS a');

        // Add the list ordering clause.
        $orderCol	= $this->state->get('list.ordering', 'a.id');
        $orderDirn	= $this->state->get('list.direction', 'asc');

        $query->order($db->escape($orderCol.' '.$orderDirn));
        return $query;
    }

    public function delete(&$pks)
    {
        // Initialise variables.
        $pks = (array) $pks;
        $table = $this->getTable();

        // Iterate the items to delete each one.
        foreach ($pks as $i => $pk) {
            if ($table->load($pk)) {
                if (!$table->delete($pk)) {
                    $this->setError($table->getError());
                    return false;
                }
            } else {
                $this->setError($table->getError());
                return false;
            }
        }

        // Clear the component's cache
        $this->cleanCache();

        return true;
    }
}
