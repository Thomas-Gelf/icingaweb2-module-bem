<?php

namespace Icinga\Module\Bem;

use Exception;
use Icinga\Application\Logger;
use Icinga\Module\Bem\Config\CellConfig;
use React\EventLoop\Factory as Loop;
use SplObjectStorage;

class MainRunner
{
    /** @var int */
    private $maxParallel;

    /** @var CellConfig */
    protected $cell;

    /** @var Loop */
    private $loop;

    /** @var string */
    protected $cellName;

    /** @var SplObjectStorage */
    protected $running;

    /** @var BemNotification[] */
    protected $queue = [];

    /** @var BemIssues */
    protected $issues;

    /** @var CellStats */
    protected $stats;

    private $isReady = false;

    public function __construct($cellName)
    {
        $this->running = new SplObjectStorage();
        $this->cellName = $cellName;
        $this->cell = CellConfig::loadByName($cellName);
        $this->reset();
    }

    protected function enqueue(BemNotification $notification)
    {
        $this->queue[] = $notification;
    }

    public function run()
    {
        $loop = $this->loop = Loop::create();

        $loop->nextTick(function () {
            $this->runFailSafe(function () {
                $this->stats->updateStats(true);
            });
        });
        $loop->addPeriodicTimer(0.5, function () {
            $this->runFailSafe(function () {
                $this->fillQueue();
            });
        });
        $loop->addPeriodicTimer(0.1, function () {
            // Hint: runQueue() is fail-safe
            $this->runQueue();
        });
        $loop->addPeriodicTimer(1, function () {
            $this->runFailSafe(function () {
                $this->stats->updateStats();
            });
        });
        $loop->addPeriodicTimer(60, function () {
            $this->runFailSafe(function () {
                $this->stats->updateStats(true);
            });
        });
        $loop->addPeriodicTimer(15, function () {
            if (! $this->isReady) {
                $this->reset();
            }
        });
        $loop->addPeriodicTimer(10, function () {
            $this->runFailSafe(function () {
                if ($this->cell->checkForFreshConfig()) {
                    $this->reset();
                }
            });
        });
        $loop->run();
    }

    protected function reset()
    {
        $this->isReady = false;
        try {
            Logger::info('Resetting BEM main runner for %s', $this->cellName);
            $this->cell->disconnect();
            $this->maxParallel = $this->cell->getMaxParallelRunners();
            $this->issues = new BemIssues($this->cell);
            $this->stats = new CellStats($this->cell);
            $this->isReady = true;
        } catch (Exception $e) {
            Logger::error(
                'Failed to reset BEM main runner for %s: %s',
                $this->cellName,
                $e->getMessage()
            );
        }
    }

    protected function fillQueue()
    {
        if (! empty($this->queue)) {
            Logger::debug('Queue not empty, not fetching new tasks');
            return;
        }

        // TODO: evaluate whether we should sync Icinga Events
        // foreach ($cell->fetchProblemEvents() as $row) {
        //     $this->enqueue(BemNotification::fromProblemQueryRow($row));
        // }

        foreach ($this->issues->fetchOverdueIssues() as $issue) {
            $this->enqueue(BemNotification::forIssue($issue));
        }
    }

    protected function runFailSafe($method)
    {
        if (! $this->isReady) {
            return;
        }

        try {
            $method();
        } catch (Exception $e) {
            Logger::error($e);
            $this->reset();
        }
    }

    protected function runQueue()
    {
        while ($this->isReady && ! $this->isRunQueueFull()) {
            $event = array_shift($this->queue);
            if ($event === null) {
                return;
            }

            $this->running->attach($event);
            $this->runFailSafe(function () use ($event) {
                $this->sendAndLogEvent($event);
            });
        }
    }

    protected function isRunQueueFull()
    {
        return $this->countRunningProcesses() < $this->maxParallel;
    }

    public function countRunningProcesses()
    {
        return $this->running->count();
    }

    protected function sendAndLogEvent(BemNotification $notification)
    {
        $poster = $this->cell->getImpactPoster();
        $this->stats->updateRunQueue(
            $this->countRunningProcesses(),
            $this->maxParallel
        );

        $poster->send($notification, $this->loop);
    }

    protected function loop()
    {
        if ($this->loop === null) {
            $this->loop = Loop::create();
        }

        return $this->loop;
    }
}
