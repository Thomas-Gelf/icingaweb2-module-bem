<?php

namespace Icinga\Module\Bem;

use Icinga\Data\ResourceFactory;
use Icinga\Exception\IcingaException;
use Icinga\Module\Bem\Config\CellConfig;
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
     * @return BemIssue[]
     */
    public function fetchIssues(CellConfig $cell)
    {
        $issues = [];
        $objects = [];
        foreach ($this->fetchProblemHosts() as $object) {
            $objects[$object->id] = $object;
        }
        foreach ($this->fetchProblemServices() as $object) {
            $objects[$object->id] = $object;
        }

        $this->enrichRowsWithVars($objects);

        foreach ($objects as $object) {
            $issues[] = BemIssue::forIcingaObject($object, $cell);
        }

        return $issues;
    }

    protected function fetchProblemHosts()
    {
        return $this->db->fetchAll(
            $this->selectHosts()
                ->where('hs.current_state > 0')
                ->where('hs.scheduled_downtime_depth = 0')
                ->where('hs.problem_has_been_acknowledged = 0')
        );
    }

    protected function fetchProblemServices()
    {
        return $this->db->fetchAll(
            $this->selectServices()
                ->where('hs.current_state = 0')
                ->where('ss.current_state > 0')
                ->where('ss.scheduled_downtime_depth = 0')
                ->where('ss.problem_has_been_acknowledged = 0')
        );
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
        $query = $this->selectHosts()->where('ho.name1 = ?', $host);

        return $this->enrichRowWithVars(
            $this->assertValidRow(
                $this->db->fetchRow($query),
                $host
            )
        );
    }

    public function getServiceStateRow($host, $service)
    {
        $query = $this->selectServices()
            ->where('so.name1 = ', $host)
            ->where('so.name2 = ?', $service);

        return $this->enrichRowWithVars($this->db->fetchRow($query));
    }

    protected function selectHosts()
    {
        return $this->db->select()->from(
            ['ho' => 'icinga_objects'],
            [
                'id'              => 'ho.object_id',
                'object_type'     => "('host')",
                'host_id'         => '(NULL)',
                'host_name'       => 'ho.name1',
                'service_name'    => '(NULL)',
                'state_type'      => "(CASE WHEN hs.state_type = 1 THEN 'HARD' ELSE 'SOFT' END)",
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
        );
    }

    protected function selectServices()
    {
        return $this->db->select()->from(
            ['so' => 'icinga_objects'],
            [
                'id'              => 'so.object_id',
                'object_type'     => "('service')",
                'host_id'         => 'hs.host_object_id',
                'host_name'       => 'so.name1',
                'service_name'    => 'so.name2',
                'state_type'      => "(CASE WHEN ss.state_type = 1 THEN 'HARD' ELSE 'SOFT' END)",
                'state'           => 'ss.current_state',
                'hard_state'      => 'CASE WHEN ss.has_been_checked = 0 OR ss.has_been_checked IS NULL THEN 99'
                    . ' ELSE CASE WHEN ss.state_type = 1 THEN ss.current_state'
                    . ' ELSE ss.last_hard_state END END',
                'is_acknowledged' => 'ss.problem_has_been_acknowledged',
                'is_in_downtime'  => 'CASE WHEN (ss.scheduled_downtime_depth = 0) THEN 0 ELSE 1 END',
                'output'          => 'ss.output',
            ]
        )->join(
            ['ss' => 'icinga_servicestatus'],
            'so.object_id = ss.service_object_id AND so.is_active = 1',
            []
        )->join(
            ['s' => 'icinga_services'],
            's.service_object_id = ss.service_object_id',
            []
        )->join(
            ['hs' => 'icinga_hoststatus'],
            'hs.host_object_id = s.host_object_id',
            []
        );
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

    protected function enrichRowsWithVars($rows)
    {
        if (empty($rows)) {
            return;
        }

        $serviceHostIds = [];
        foreach ($rows as $row) {
            if ($row->host_id) {
                if (! array_key_exists($row->host_id, $serviceHostIds)) {
                    $serviceHostIds[$row->host_id] = [];
                }
                $serviceHostIds[$row->host_id][] = $row->id;
            }
        }

        $query = $this->db->select()->from(
            ['cv' => 'icinga_customvariablestatus'],
            ['cv.object_id', 'cv.varname', 'cv.varvalue']
        )->where('object_id IN (?)', array_keys($rows));

        foreach ($this->db->fetchAll($query) as $row) {
            $key = $rows[$row->object_id]->service_name === null
                ? 'host.vars.' . $row->varname
                : 'service.vars.' . $row->varname;

            $rows[$row->object_id]->$key = $row->varvalue;
        }

        if (empty($serviceHostIds)) {
            return;
        }

        $query = $this->db->select()->from(
            ['cv' => 'icinga_customvariablestatus'],
            ['cv.object_id', 'cv.varname', 'cv.varvalue']
        )->where('object_id IN (?)', array_keys($serviceHostIds));

        foreach ($this->db->fetchAll($query) as $row) {
            $key = 'host.vars.' . $row->varname;

            foreach ($serviceHostIds[$row->object_id] as $id) {
                $rows[$id]->$key = $row->varvalue;
            }
        }
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
