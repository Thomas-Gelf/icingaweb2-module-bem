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

    /** @var BemIssue[] */
    protected $queue = [];

    /** @var BemIssues */
    protected $issues;

    /** @var CellStats */
    protected $stats;

    /** @var IdoDb */
    protected $ido;

    private $isReady = false;

    public function __construct($cellName)
    {
        $this->running = new SplObjectStorage();
        $this->cellName = $cellName;
        $this->cell = CellConfig::loadByName($cellName);
        $this->reset();
    }

    protected function enqueue(BemIssue $issue)
    {
        $this->queue[] = $issue;
        $this->stats->updateRunQueue(
            $this->countRunningProcesses(),
            count($this->queue)
        );
    }

    public function run()
    {
        $loop = $this->loop = Loop::create();

        $loop->nextTick(function () {
            $this->runFailSafe(function () {
                $this->issues->refreshIssues();
                $this->refreshIdoIssues();
                $this->stats->updateStats(true);
                $this->fillQueue();
            });
        });
        $loop->addPeriodicTimer(0.5, function () {
            $this->runFailSafe(function () {
                $this->fillQueue();
            });
        });
        $loop->addPeriodicTimer(5, function () {
            $this->runFailSafe(function () {
                $this->refreshIdoIssues();
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
            if ($this->ido !== null) {
                $this->ido->getDb()->closeConnection();
            }
            $this->ido = IdoDb::fromMonitoringModule();
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

        foreach ($this->issues->getDueIssues() as $issue) {
            if (! $this->running->contains($issue)) {
                $this->enqueue($issue);
            }
        }
    }

    protected function refreshIdoIssues()
    {
        Logger::debug('Refreshing IDO issues');
        $this->issues->refreshFromIdo($this->ido);
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
            /** @var BemIssue $event */
            $issue = array_shift($this->queue);
            $this->stats->updateRunQueue(
                $this->countRunningProcesses(),
                count($this->queue)
            );

            if ($issue === null) {
                return;
            }

            $this->running->attach($issue);
            $this->runFailSafe(function () use ($issue) {
                $this->sendAndLogEvent($issue);
            });
        }
    }

    public function notifyIssueIsDone(BemIssue $issue)
    {
        $this->running->detach($issue);
        $this->stats->updateRunQueue(
            $this->countRunningProcesses(),
            count($this->queue)
        );
    }

    protected function isRunQueueFull()
    {
        return $this->countRunningProcesses() >= $this->maxParallel;
    }

    public function countRunningProcesses()
    {
        return $this->running->count();
    }

    protected function sendAndLogEvent(BemIssue $issue)
    {
        $poster = $this->cell->getImpactPoster();
        $this->stats->updateRunQueue(
            $this->countRunningProcesses(),
            count($this->queue)
        );

        // TODO: When done, detach from running queue!
        $poster->send($issue, $this->loop, $this);
    }

    protected function loop()
    {
        if ($this->loop === null) {
            $this->loop = Loop::create();
        }

        return $this->loop;
    }
}
