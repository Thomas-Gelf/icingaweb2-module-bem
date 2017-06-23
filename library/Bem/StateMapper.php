<?php

namespace Icinga\Module\Bem;

use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Service;

class StateMapper
{
    protected static $hostMap = array(
        'UP'          => 'OK',
        'DOWN'        => 'CRITICAL',
        'UNREACHABLE' => 'MINOR',
    );

    protected static $serviceMap = array(
        'OK'       => 'OK',
        'WARNING'  => 'MINOR',
        'UNKNOWN'  => 'MAJOR',
        'CRITICAL' => 'CRITICAL',
    );

    protected static $downGradeMap = array(
        'CRITICAL' => 'WARNING',
        'MINOR'    => 'WARNING',
        'MAJOR'    => 'WARNING',
    );

    protected static $icingaHostStates = array(
        'UP',
        'DOWN',
        'UNREACHABLE',
        // 99 => 'PENDING'
    );

    protected static $icingaServiceStates = array(
        'OK',
        'WARNING',
        'CRITICAL',
        'UNKNOWN',
        // 99 => 'PENDING'
    );

    public static function getFinalSeverityForObject(MonitoredObject $object)
    {
        if (static::objectStateShouldBeDowngraded($object)) {
            return static::downgradeBmcState(
                static::bmcSeverityForObject($object)
            );
        } else {
            return static::bmcSeverityForObject($object);
        }
    }

    protected static function shouldBeIgnored($row)
    {
// if prio 5 -> drop
// drop service where prio 3-5 and state == warning
// drop end of life -> preg_match('/^end/i', $row->{'host.vars.cmdb_state'})
// no prio -> don't send, query
    }

    public static function objectStateShouldBeDowngraded(MonitoredObject $object)
    {
        return $row->{'host.vars.cmdb_state'} !== 'in use' || $row->{'host.vars.maintenance'} === 'true';
        // $maintperiod == 1){
        // ->return true
// -> downtime in 

        return false;
    }

    public static function getIcingaHostState($state)
    {
        // TODO: hard states?
        return static::$hostMap[
            static::$icingaHostStates[$state]
        ];
    }

    public static function getIcingaServiceState($state)
    {
        return static::$serviceMap[
            static::$icingaServiceStates[$state]
        ];
    }

    protected static function icingaHostState(Host $host)
    {
        // TODO: hard states?
        return static::$hostMap[
            static::$icingaHostStates[$host->host_state]
        ];
    }

    protected static function icingaServiceState(Service $service)
    {
        return static::$serviceMap[
            static::$icingaServiceStates[$service->service_state]
        ];
    }

    public static function bmcSeverityForObject(MonitoredObject $object)
    {
        // TODO: hardstatename
        if ($object instanceof Host) {
            return static::icingaHostState($object);
        } else {
            return static::icingaServiceState($object);
        }
    }

    public static function downgradeBmcState($state)
    {
        if (array_key_exists($state, static::$downGradeMap)) {
            return static::$downGradeMap[$state];
        } else {
            return $state;
        }
    }
}
