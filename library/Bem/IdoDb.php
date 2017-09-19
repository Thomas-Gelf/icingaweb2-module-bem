<?php

namespace Icinga\Module\Bem;

use Icinga\Data\ResourceFactory;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Zend_Db_Adapter_Abstract as DbAdapter;

/**
 * Class IdoDb
 *
 * Small IDO abstraction layer
 */
class IdoDb
{
    /** @var DbAdapter */
    protected $db;

    /**
     * IdoDb constructor.
     * @param DbAdapter $db
     */
    public function __construct(DbAdapter $db)
    {
        $this->db = $db;
    }

    /**
     * @return DbAdapter
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * Instantiate with a given Icinga Web 2 resource name
     *
     * @param $name
     * @return static
     */
    public static function fromResourceName($name)
    {
        return new static(
            ResourceFactory::create($name)->getDbAdapter()
        );
    }

    /**
     * Borrow the database connection from the monitoring module
     *
     * @return static
     */
    public static function fromMonitoringModule()
    {
        return new static(
            MonitoringBackend::instance()->getResource()->getDbAdapter()
        );
    }
}
