<?php

namespace Icinga\Module\Bem\Clicommands;

use Icinga\Cli\Command as CliCommand;
use Icinga\Module\Bem\IdoDb;
use Icinga\Module\Bem\MainRunner;

class Command extends CliCommand
{
    public function init()
    {
        // We need ipl3rdparty, monitoring
        $this->app->getModuleManager()->loadEnabledModules();
    }

    /**
     * @return MainRunner
     * @throws \Icinga\Exception\MissingParameterException
     */
    protected function getRunner()
    {
        return new MainRunner($this->params->getRequired('cell'));
    }

    /**
     * @return IdoDb
     */
    protected function getIdo()
    {
        return IdoDb::fromMonitoringModule();
    }
}
