<?php

namespace Icinga\Module\Bem\Clicommands;

use Exception;
use Icinga\Application\Logger;
use Icinga\Cli\Command;
use Icinga\Module\Bem\Config;
use Icinga\Module\Bem\Notifications;
use Icinga\Module\Bem\Cell;
use Icinga\Module\Bem\Event;
use Icinga\Module\Bem\IdoDb;
use Icinga\Module\Bem\ImpactPoster;

class EventCommand extends Command
{
    /** @var Config */
    private $bem;

    public function sendallAction()
    {
        $shouldRun = true;
        while ($shouldRun) {
            try {
                $this->runOnce();
            } catch (Exception $e) {
                Logger::error($e->getMessage());
                sleep(10);
                try {
                    $this->forgetEverything();
                } catch (Exception $e) {
                    // There isn't much we can do here
                }
            }

            sleep(5);
        }
    }

    public function sendAction()
    {
        $cell = $this->requireCell();
        $issues = $cell->notifications();
        $poster = $cell->msend();
        $host    = $this->params->getRequired('host');
        $service = $this->params->get('service');
        $ido = IdoDb::fromMonitoringModule();
        $event = Event::fromIdo($ido, $host, $service);
        $poster->setEvent($event);
        printf("Sending event for %s\n", $event->getUniqueObjectName());
        $issues->persistPosterResult($poster->send());
    }

    protected function runOnce()
    {
        $cell = $this->requireCell();
        $issues = $cell->notifications();
        $poster = $cell->msend();

        foreach ($cell->fetchProblemEvents() as $row) {
            $event = Event::fromProblemQueryRow($row);
            $this->sendAndLogEvent($event, $poster, $issues);
        }

        foreach ($cell->fetchOverDueEvents() as $event) {
            if ($event->isIssue()) {
                $this->sendAndLogEvent($event, $poster, $issues);
            } else {
                $this->sendAndLogEvent($event, $poster, $issues);
                $issues->discardEvent($event);
            }
        }
    }

    protected function sendAndLogEvent(Event $event, ImpactPoster $poster, Notifications $issues)
    {
        $poster->setEvent($event);
        Logger::info("Sending event for %s\n", $event->getUniqueObjectName());
        $issues->persistPosterResult($poster->send());
    }

    /**
     * @return Cell
     */
    protected function requireCell()
    {
        return $this->bem()->getCell($this->params->getRequired('cell'));
    }

    /**
     * @return Config
     */
    protected function bem()
    {
        if ($this->bem === null) {
            $this->bem = new Config();
        }

        return $this->bem;
    }

    protected function forgetEverything()
    {
        unset($this->bem);
    }
}
