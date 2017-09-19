<?php

namespace Icinga\Module\Bem;

use Icinga\Exception\ProgrammingError;
use Zend_Db_Adapter_Abstract as DbAdapter;
use Zend_Db_Select as DbSelect;

class QueryHelper
{
    /** @var DbAdapter */
    protected $db;

    public function __construct(DbAdapter $db)
    {
        $this->db = $db;
    }

    public function selectProblemsForBmc()
    {
        $hardState = 'CASE WHEN hs.state_type THEN hs.current_state'
            . ' ELSE hs.last_hard_state END';
        $serviceHardState = 'CASE WHEN ss.state_type THEN ss.current_state'
            . ' ELSE ss.last_hard_state END';
        return $this->db->select()->union([
            $this->getHostQuery()->where("$hardState > 0"),
            $this->getServiceQuery()->where("$serviceHardState > 0")
        ], DbSelect::SQL_UNION_ALL)->order('host_name')->order('service_name');
    }

    public function selectBmcObjects()
    {
        return $this->db->select()->union([
            $this->getHostQuery(),
            $this->getServiceQuery()
        ], DbSelect::SQL_UNION_ALL)->order('host_name')->order('service_name');
    }

    public function getDb()
    {
        return $this->db;
    }

    protected function getHostQuery()
    {
        $hardState = 'CASE WHEN hs.state_type THEN hs.current_state'
            . ' ELSE hs.last_hard_state END';
        $query = $this->getBaseHostQuery()->columns([
            'host_name'            => 'ho.name1 COLLATE latin1_general_ci',
            'service_name'         => '(NULL)',
            'host_hard_state'      => "($hardState)",
            'host_acknowledged'    => $this->ackColumn('hs'),
            'host_in_downtime'     => $this->downtimeColumn('hs'),
            'service_hard_state'   => '(NULL)',
            'service_acknowledged' => '(NULL)',
            'service_in_downtime'  => '(NULL)',
            'output'               => 'hs.output',
        ]);

        return $query;
    }

    protected function getBaseHostQuery()
    {
        return $this->db->select()->from(
            ['h' => 'icinga_hosts'],
            []
        )->join(
            ['ho' => 'icinga_objects'],
            'h.host_object_id = ho.object_id AND ho.is_active = 1',
            []
        )->join(
            ['hs' => 'icinga_hoststatus'],
            'ho.object_id = hs.host_object_id',
            []
        );
    }

    protected function getServiceQuery()
    {
        $hostHardState = 'CASE WHEN hs.state_type THEN hs.current_state'
            . ' ELSE hs.last_hard_state END';
        $serviceHardState = 'CASE WHEN ss.state_type THEN ss.current_state'
            . ' ELSE ss.last_hard_state END';
        $query = $this->getBaseServiceQuery()->columns([
            'host_name'            => 'ho.name1 COLLATE latin1_general_ci',
            'service_name'         => 'so.name2 COLLATE latin1_general_ci',
            'host_hard_state'      => "($hostHardState)",
            'host_acknowledged'    => $this->ackColumn('hs'),
            'host_in_downtime'     => $this->downtimeColumn('hs'),
            'service_hard_state'   => "($serviceHardState)",
            'service_acknowledged' => $this->ackColumn('ss'),
            'service_in_downtime'  => $this->downtimeColumn('ss'),
            'output'               => 'ss.output',
        ]);

        return $query;
    }

    protected function getBaseServiceQuery()
    {
        return $this->db->select()->from(
            ['s' => 'icinga_services'],
            []
        )->join(
            ['so' => 'icinga_objects'],
            's.service_object_id = so.object_id AND so.is_active = 1',
            []
        )->join(
            ['ss' => 'icinga_servicestatus'],
            'so.object_id = ss.service_object_id',
            []
        )->join(
            ['h' => 'icinga_hosts'],
            's.host_object_id = h.host_object_id',
            []
        )->join(
            ['ho' => 'icinga_objects'],
            'h.host_object_id = ho.object_id AND ho.is_active = 1',
            []
        )->join(
            ['hs' => 'icinga_hoststatus'],
            'ho.object_id = hs.host_object_id',
            []
        );
    }

    protected function addCustomVars($query, $type, $names)
    {
        foreach ($names as $name) {
            $this->addCustomVar($query, $type, $name);
        }

        return $query;
    }

    protected function splitVarName($varName)
    {
        if (preg_match('/^(host|service)/\.vars\.(.+)$/', $varName, $match)) {
            return [$match[1], $match[2]];
        } else {
            throw new ProgrammingError(
                'Varname expected, got %s',
                $varName
            );
        }
    }

    public function addCustomVar($query, $type, $name = null, $required = false)
    {
        if ($name === null) {
            list($type, $name) = $this->splitVarName($type);
        }
        $db = $this->db;
        $alias = $db->quoteIdentifier(($type === 'host' ? 'hcv_' : 'scv_') . $name);
        $column = $db->quoteIdentifier(["$type.vars.$name"]);

        if ($type === 'service') {
            $left = 'so.object_id';
        } else {
            $left = 'ho.object_id';
        }

        $condition = $db->quoteInto(
            "$left = $alias.object_id AND $alias.varname = ? COLLATE latin1_general_ci",
            $name
        );

        $method = $required ? 'join' : 'joinLeft';
        $query->$method(
            [$alias => 'icinga_customvariablestatus'],
            $condition,
            [$column => "$alias.varvalue"]
        );

        return $this;
    }

    public function requireCustomVar($query, $type, $name = null)
    {
        if ($name === null) {
            list($type, $name) = $this->splitVarName($type);
        }
        return $this->addCustomVar($query, $type, $name, true);
    }

    protected function ackColumn($alias)
    {
        return "(CASE WHEN $alias.problem_has_been_acknowledged > 0 THEN 'y' ELSE 'n' END)";
    }

    protected function downtimeColumn($alias)
    {
        return "(CASE WHEN $alias.scheduled_downtime_depth > 0 THEN 'y' ELSE 'n' END)";
    }
}
