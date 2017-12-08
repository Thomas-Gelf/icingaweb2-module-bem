<?php

namespace Icinga\Module\Bem\Clicommands;

use Icinga\Cli\Command;
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
        $this->getNewRunner()->run();
    }

    public function sendAction()
    {
        $this->getNewRunner()->runOnceFor(
            $this->params->getRequired('host'),
            $service = $this->params->get('service')
        );
    }

    /**
     * @return MainRunner
     */
    protected function getNewRunner()
    {
        return new MainRunner($this->params->get('cell'));
    }
}
