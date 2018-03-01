<?php

namespace Icinga\Module\Bem\Clicommands;

use Icinga\Module\Bem\BemNotification;
use Icinga\Module\Bem\Config\CellConfig;

class NotificationCommand extends Command
{
    /**
     * icingacli bem notification send --cell <cell_name> [options]
     *
     * --host_name <host>
     * --service_name <service>
     * --host.vars.<varname> <varvalue>
     * --service.vars.<varname> <varvalue>
     * --state <state>
     * --output <output>
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

        if ($config->wants((object) $object)) {
            $poster = $config->getImpactPoster();
            echo "DEBUG: " . $poster->getCommandString($notification) . "\n";
            $poster->send($notification);
        } else {
            echo "This is not a problem, might be cleared\n";
        }
    }
}
