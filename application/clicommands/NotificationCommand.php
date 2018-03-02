<?php

namespace Icinga\Module\Bem\Clicommands;

use Icinga\Module\Bem\BemIssue;
use Icinga\Module\Bem\BemNotification;
use Icinga\Module\Bem\Config\CellConfig;

class NotificationCommand extends Command
{
    /**
     * USAGE
     * -----
     * icingacli bem notification schedule --cell <cell_name> [options]
     *
     * OPTIONS
     * -------
     *   --host_name <host>
     *   --service_name <service>
     *   --host.vars.<varname> <varvalue>
     *   --service.vars.<varname> <varvalue>
     *   --state <state>
     *   --output <output>
     */
    public function scheduleAction()
    {
        $p = $this->params;
        $config = CellConfig::loadByName($p->shiftRequired('cell'));
        $p->getRequired('host_name');
        $p->getRequired('state');
        $p->getRequired('output');

        $object = $this->params->getParams();
        $object['state_type'] = 'HARD';
        if (array_key_exists('service_name', $object)) {
            $object['object_type'] = 'service';
        } else {
            $object['object_type'] = 'host';
            $object['service_name'] = null;
        }
        $object = (object) $object;
        if ($config->wantsIcingaObject($object)) {
            $issue = BemIssue::forIcingaObject($object, $config);
            $isNew = $issue->isNew();
            $issue->scheduleNextNotification()->store();
            if ($isNew) {
                echo "Scheduled a new issue\n";
            } else {
                echo "Rescheduled an existing issue\n";
            }
        } else {
            echo "This is not a problem, not scheduled\n";
        }
    }

    /**
     * USAGE
     * -----
     * icingacli bem notification schedule --cell <cell_name> [options]
     *
     * OPTIONS
     * -------
     *   --host_name <host>
     *   --service_name <service>
     *   --host.vars.<varname> <varvalue>
     *   --service.vars.<varname> <varvalue>
     *   --state <state>
     *   --output <output>
     */
    public function sendAction()
    {
        $p = $this->params;
        $config = CellConfig::loadByName($p->shiftRequired('cell'));
        $p->getRequired('host_name');
        $p->getRequired('state');
        $p->getRequired('output');

        $object = $this->params->getParams();
        $object['state_type'] = 'HARD';
        if (array_key_exists('service_name', $object)) {
            $object['object_type'] = 'service';
        } else {
            $object['object_type'] = 'host';
            $object['service_name'] = null;
        }

        $notification = BemNotification::forIcingaObject((object) $object, $config);
        if ($config->wantsIcingaObject((object) $object)) {
            $poster = $config->getImpactPoster();
            echo $poster->getCommandString($notification) . "\n";
        } else {
            echo "This is not a problem, might be cleared\n";
        }
    }
}
