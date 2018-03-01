<?php

namespace Icinga\Module\Bem\Clicommands;

class DaemonCommand extends Command
{
    public function runAction()
    {
        $this->getRunner()->run();
    }
}
