<?php

namespace Icinga\Module\Bem;

use Icinga\Data\ResourceFactory;
use Icinga\Exception\IcingaException;
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

    public function getStateRowFor($host, $service = null)
    {
        if ($service === null) {
            return $this->getHostStateRow($host);
        } else {
            return $this->getServiceStateRow($host, $service);
        }
    }

    public function getHostStateRow($host)
    {
        $query = $this->db->select()->from(
            ['ho' => 'icinga_objects'],
            [
                'id'              => 'ho.object_id',
                'host_name'       => 'ho.name1',
                'service_name'    => '(NULL)',
                'state_type'      => 'hs.state_type',
                'state'           => 'hs.current_state',
                'hard_state'      => 'CASE WHEN hs.has_been_checked = 0 OR hs.has_been_checked IS NULL THEN 99'
                                   . ' ELSE CASE WHEN hs.state_type = 1 THEN hs.current_state'
                                   . ' ELSE hs.last_hard_state END END',
                'is_acknowledged' => 'hs.problem_has_been_acknowledged',
                'is_in_downtime'  => 'CASE WHEN (hs.scheduled_downtime_depth = 0) THEN 0 ELSE 1 END',
                'output'          => 'hs.output',
            ]
        )->join(
            ['hs' => 'icinga_hoststatus'],
            'ho.object_id = hs.host_object_id AND ho.is_active = 1',
            []
        )->where('ho.name1 = ?', $host);

        return $this->enrichRowWithVars(
            $this->assertValidRow(
                $this->db->fetchRow($query),
                $host
            )
        );
    }

    public function getServiceStateRow($host, $service)
    {
        $query = $this->db->select()->from(
            ['so' => 'icinga_objects'],
            [
                'id'              => 'so.object_id',
                'host_name'       => 'so.name1',
                'service_name'    => 'so.name2',
                'state_type'      => 'ss.state_type',
                'state'           => 'ss.current_state',
                'hard_state'      => 'CASE WHEN shs.has_been_checked = 0 OR ss.has_been_checked IS NULL THEN 99'
                                   . ' ELSE CASE WHEN ss.state_type = 1 THEN ss.current_state'
                                   . ' ELSE ss.last_hard_state END END',
                'is_acknowledged' => 'ss.problem_has_been_acknowledged',
                'is_in_downtime'  => 'CASE WHEN (ss.scheduled_downtime_depth = 0) THEN 0 ELSE 1 END',
                'output'          => 'hs.output',
            ]
        )->join(
            ['ss' => 'icinga_servicestatus'],
            'so.object_id = ss.service_object_id AND so.is_active = 1',
            []
        )->where('so.name1 = ', $host)->where('so.name2 = ?', $service);

        return $this->enrichRowWithVars($this->db->fetchRow($query));
    }

    protected function assertValidRow($row, $host, $service = null)
    {
        if (! is_object($row)) {
            throw new IcingaException('Not found');
        }

        return $row;
    }

    protected function enrichRowWithVars($row)
    {
        $query = $this->db->select()->from(
            ['cv' => 'icinga_customvariablestatus'],
            ['cv.varname', 'cv.varvalue']
        )->where('object_id = ?', $row->id);

        foreach ($this->db->fetchPairs($query) as $key => $value) {
            if ($key === 'host_name') {
                continue;
            }

            $row->$key = $value;
        }

        return $row;
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
