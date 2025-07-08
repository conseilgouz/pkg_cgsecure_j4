<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\Table;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Table\Table;
use Joomla\CMS\Versioning\VersionableTableInterface;
use Joomla\Database\DatabaseDriver;

class ConfigTable extends Table implements VersionableTableInterface
{
    /**
     * An array of key names to be json encoded in the bind function
     *
     * @var    array
     * @since  4.0.0
     */
    protected $_jsonEncode = ['params', 'metadata', 'urls', 'images'];
    /**
     * Indicates that columns fully support the NULL value in the database
     *
     * @var    boolean
     * @since  4.0.0
     */
    protected $_supportNullValue = true;

    /**
     * Constructor
     *
     * @param object Database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_cgsecure.config';

        parent::__construct('#__cgsecure_config', 'name', $db);
    }
    /**
     *  Store method
     *
     *  @param   string  $key  The config name
     */
    public function store($key = 'config')
    {
        $db    = $this->_db;
        $table = $this->_tbl;
        $key   = empty($this->name) ? $key : $this->name;

        // Check if key exists
        $result = $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName($this->_tbl))
                ->where($db->quoteName('name') . ' = :key')
                ->bind(':key', $key, \Joomla\Database\ParameterType::STRING)
        )->loadResult();

        $exists = $result > 0 ? true : false;

        // Prepare object to be saved
        $data = new \stdClass();
        $data->name   = $key;
        $data->params = $this->params;
        if ($exists) {
            return $db->updateObject($table, $data, 'name');
        }

        return $db->insertObject($table, $data);
    }
    /**
     * Get the type alias for the history table
     *
     * @return  string  The alias as described above
     *
     * @since   4.0.0
     */
    public function getTypeAlias()
    {
        return $this->typeAlias;
    }
    /**
     * Get Params record
     * @return unknown
     */
    public function getSecureParams()
    {

        $db    = $this->_db;
        $table = $this->_tbl;
        $key   = 'config';

        // Check if key exists
        $result = $db->setQuery(
            $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName($this->_tbl))
            ->where($db->quoteName('name') . ' = :key')
            ->bind(':key', $key, \Joomla\Database\ParameterType::STRING)
        )->loadObject();

        $this->resaparams = $result;
        return $this->resaparams;
    }
    /**
     * update Params record
     * @return unknown
     */
    public function updateSecureParams($params)
    {

        $db    = $this->_db;
        $table = $this->_tbl;
        $key   = 'config';

        $data = new \stdClass();
        $data->name   = $key;
        $data->params = $params;
        return $db->updateObject($table, $data, 'name');
    }
    public function getKeyName($multiple = false)
    {
        return 'name';
    }
}
