<?php

namespace Icinga\Module\Bem\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Bem\IdoDb;
use Icinga\Module\Bem\MainRunner;

class EventCommand extends Command
{
    public function init()
    {
        // We need ipl3rdparty, monitoring
        $this->app->getModuleManager()->loadEnabledModules();
    }

    public function senderAction()
    {
        $this->getRunner()->run();
    }

    public function sendAction()
    {
        $ido = IdoDb::fromMonitoringModule();

        $object = $ido->fetchObject(
            $this->params->getRequired('host'),
            $this->params->get('service')
        );
        $this->getRunner()->runOnceFor($object);
    }

    /**
     * @return MainRunner
     */
    protected function getRunner()
    {
        return new MainRunner($this->params->getRequired('cell'));
    }
}
