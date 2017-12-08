<?php

namespace Icinga\Module\Bem;

use Icinga\Application\Logger;
use React\EventLoop\Factory as Loop;
use SplObjectStorage;

class MainRunner
{
    private $maxParallel = 3;

    /** @var Config */
    private $bem;

    /** @var Cell */
    protected $cell;

    /** @var Loop */
    private $loop;

    protected $cellName;

    /** @var SplObjectStorage */
    protected $running;

    protected $queue = [];

    public function __construct($cellName = null)
    {
        $this->running = new SplObjectStorage();
        $this->cellName = $cellName;
        $this->getCell();
    }

    protected function getCell()
    {
        if ($this->cell === null) {
            $bem = $this->bem();
            if ($this->cellName === null) {
                $name = $bem->getDefaultCellName();
            } else {
                $name = $this->cellName;
            }
            $this->cell = $bem->getCell($name);
        }

        return $this->cell;
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

    protected function enqueue(Event $event)
    {
        $this->queue[] = $event;
    }

    /**
     * @return ImpactPoster
     */
    protected function msend()
    {
        return $this->getCell()->msend();
    }

    public function run()
    {
        $loop = $this->loop = Loop::create();

        $loop->addPeriodicTimer(0.5, function () {
            $this->fillQueue();
        });
        $loop->addPeriodicTimer(0.1, function () {
            $this->runQueue();
        });
        $loop->run();
    }

    public function runOnceFor($host, $service = null)
    {
        $this->sendAndLogEvent(
            Event::fromProblemQueryRow(
                $this->getCell()->fetchSingleObject($host, $service)
            )
        );
    }

    protected function fillQueue()
    {
        $cell = $this->getCell();
        if (! empty($this->queue)) {
            Logger::debug('Queue not empty, not fetching new tasks');
            return;
        }

        foreach ($cell->fetchProblemEvents() as $row) {
            $this->enqueue(Event::fromProblemQueryRow($row));
        }

        foreach ($cell->fetchOverDueEvents() as $event) {
            $this->enqueue($event);
        }
    }

    protected function runQueue()
    {
        Logger::info(
            '%d/%d running, %d in queue',
            $this->running->count(),
            $this->maxParallel,
            count($this->queue)
        );

        while (! $this->isRunQueueFull()) {
            $event = array_shift($this->queue);
            if ($event === null) {
                return;
            }

            $this->running->attach($event);
            $this->sendAndLogEvent($event);
        }
    }

    protected function isRunQueueFull()
    {
        return $this->running->count() < $this->maxParallel;
    }

    protected function sendAndLogEvent(Event $event)
    {
        $poster = $this->msend();
        Logger::debug("Sending event for %s\n", $event->getUniqueObjectName());
        $poster->send($event, [$this, 'persistEventResult'], $this->loop);
    }

    public function persistEventResult(Event $event)
    {
        $issues = $this->cell->notifications();
        $this->running->detach($event);
        $issues->persistEventResult($event);

        if ($event->hasBeenSent() && ! $event->isIssue()) {
            $issues->discardEvent($event);
        }
    }

    protected function forgetEverything()
    {
        $this->bem = null;
        $this->cell = null;
    }

    protected function loop()
    {
        if ($this->loop === null) {
            $this->loop = Loop::create();
        }

        return $this->loop;
    }
}
