<?php

namespace Icinga\Module\Bem\Clicommands;

use Icinga\Module\Bem\CellStats;
use Icinga\Module\Bem\Config\CellConfig;

class CheckCommand extends Command
{
    /**
     * USAGE
     * -----
     *
     * icingacli bem check queue --cell <cell_name>
     */
    public function queueAction()
    {
        $cell = CellConfig::loadByName($this->params->shiftRequired('cell'));
        $stats = new CellStats($cell);
        if ($stats->isOutdated()) {
            printf(
                "%s BEM Cell '%s' hasn't been updated since %s\n",
                $this->screen->colorize('[CRITICAL]', 'red'),
                $cell->getName(),
                $stats->get('ts_last_update')
            );
            exit(2);
        }

        if ($stats->get('queue_size') > 100) {
            printf(
                "BEM Cell '%s' has a large queue with %d pending notifications",
                $cell->getName(),
                $stats->get('queue_size')
            );

            echo $this->createPerformanceDataString($stats);
            exit(2);
        } elseif ($stats->get('queue_size') > $stats->get('max_parallel_processes')) {
            printf(
                "BEM Cell '%s' has %d pending notifications",
                $cell->getName(),
                $stats->get('queue_size')
            );

            echo $this->createPerformanceDataString($stats);
            exit(1);
        } else {
            printf(
                "BEM Cell '%s' is running fine",
                $cell->getName()
            );

            echo $this->createPerformanceDataString($stats);
            exit(0);
        }
    }

    protected function createPerformanceDataString(CellStats $stats)
    {
        return sprintf(
            "|sent_events=%sc;pending_notifications=%d;running_processes=%d;0;%d\n",
            $stats->get('event_counter'),
            $stats->get('queue_size'),
            $stats->get('running_processes'),
            $stats->get('max_parallel_processes')
        );
    }
}
