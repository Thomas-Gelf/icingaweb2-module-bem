<?php

namespace Icinga\Module\Bem\Clicommands;

use Icinga\Module\Bem\BemNotification;
use Icinga\Module\Bem\Config\CellConfig;

class EventCommand extends Command
{

    public function senderAction()
    {
        $this->getRunner()->run();
    }

    public function sendAction()
    {
        $object = $this->getIdo()->fetchObject(
            $this->params->getRequired('host'),
            $this->params->get('service')
        );
        $this->getRunner()->runOnceFor($object);
    }
}
