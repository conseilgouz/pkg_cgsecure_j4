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

class IpTable extends Table implements VersionableTableInterface
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
        $this->typeAlias = 'com_cgsecure.logs';

        parent::__construct('#__cg_rejected_ip', 'id', $db);
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

}
