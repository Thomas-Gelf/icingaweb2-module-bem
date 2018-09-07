<?php

namespace Icinga\Module\Bem;

use Exception;
use Icinga\Application\Logger;
use Icinga\Module\Bem\Config\CellConfig;
use React\EventLoop\Factory as Loop;
use React\EventLoop\TimerInterface;
use SplObjectStorage;

/**
 * Class MainRunner
 *
 * Requires a BEM Cell and keeps it in Sync with the configured IDO instance
 *
 * @package Icinga\Module\Bem
 */
class MainRunner
{
    /** @var int */
    private $maxParallel;

    /** @var CellConfig */
    protected $cell;

    /** @var \React\EventLoop\LoopInterface */
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

    /** @var CellHealth */
    protected $health;

    /** @var IdoDb */
    protected $ido;

    private $isReady = false;

    private $isMaster;

    /** @var TimerInterface|null */
    private $promoteToMaster;

    /**
     * MainRunner constructor.
     *
     * @param $cellName
     */
    public function __construct($cellName)
    {
        $this->running = new SplObjectStorage();
        $this->cellName = $cellName;
        $this->cell = CellConfig::loadByName($cellName);
        $this->reset();
    }

    /**
     * Put the given issue into our run queue
     *
     * @param BemIssue $issue
     */
    protected function enqueue(BemIssue $issue)
    {
        $this->queue[] = $issue;
        $this->stats->updateRunQueue(
            $this->countRunningProcesses(),
            count($this->queue)
        );
    }

    /**
     * @param BemIssue $issue
     * @throws \Icinga\Exception\IcingaException
     */
    public function forgetIssue(BemIssue $issue)
    {
        $this->issues->forget($issue);
    }

    /**
     * Run the main loop
     */
    public function run()
    {
        $loop = $this->loop();

        $loop->addSignal(SIGINT, $func = function ($signal) use (&$func) {
            $this->shutdownWithSignal($signal, $func);
        });
        $loop->addSignal(SIGTERM, $func = function ($signal) use (&$func) {
            $this->shutdownWithSignal($signal, $func);
        });
        $loop->futureTick(function () {
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
        $loop->addPeriodicTimer(3, function () {
            $this->runFailSafe(function () {
                $this->checkHealth();
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

    protected function checkHealth()
    {
        $health = $this->health;
        $health->refresh();
        $cellName = $this->cellName;

        if ($this->isMaster === null
            && ($this->cell->shouldBeMaster() || ! $this->cell->hasFailOver())) {
            Logger::info("I will run as master for $cellName, no fail-over configured");
            $this->promote();

            return;
        }

        if ($this->isMaster && $health->shouldBeMaster()) {
            return;
        }

        if ($this->isMaster && ! $health->shouldBeMaster()) {
            Logger::info("I'm master for $cellName, the other instance is running fine");
            $this->demote();
            return;
        }

        if (! $this->isMaster && $health->shouldBeMaster()) {
            $this->schedulePromotion();
            return;
        }
    }

    protected function schedulePromotion()
    {
        if ($this->promoteToMaster === null) {
            $timeout = $this->cell->get('main', 'defer_promotion', 30);
            $cellName = $this->cellName;
            Logger::warning("I'm standby, will promote to master for $cellName in $timeout seconds");
            $this->promoteToMaster = $this->loop()->addTimer($timeout, function () {
                $this->runFailSafe(function () {
                    $this->promote();
                });
            });

        }
    }

    protected function promote()
    {
        Logger::info('I will now become master for ' . $this->cellName);
        $this->cancelPendingPromotion();
        $this->health->promote();
        Logger::info('I\'m now master for ' . $this->cellName);
        $this->isMaster = true;
    }

    protected function cancelPendingPromotion()
    {
        if ($this->promoteToMaster !== null) {
            $this->loop()->cancelTimer($this->promoteToMaster);
            $this->promoteToMaster = null;
        }
    }

    protected function demote()
    {
        Logger::info('I will now become standby for ' . $this->cellName);
        $this->cancelPendingPromotion();
        $this->health->demote();
        Logger::info('I\'m now standby for ' . $this->cellName);
        $this->isMaster = false;
        $this->queue = [];
    }

    /**
     * Reset all connections, config, issues
     */
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
            $this->health = new CellHealth($this->cell);
            $this->health->refreshProcessInfo();
            $this->checkHealth();
            $this->isReady = true;
        } catch (Exception $e) {
            Logger::error(
                'Failed to reset BEM main runner for %s: %s',
                $this->cellName,
                $e->getMessage()
            );
        }
    }

    /**
     * Enqueue all due issues
     *
     * @throws \Icinga\Exception\IcingaException
     */
    protected function fillQueue()
    {
        if (! $this->isMaster) {
            return;
        }
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

    /**
     * Refresh current issues by comparing them to those in the IDO
     *
     * @throws \Icinga\Exception\IcingaException
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function refreshIdoIssues()
    {
        Logger::debug('Refreshing IDO issues');
        $this->issues->refreshFromIdo($this->ido);
    }

    /**
     * Run the given callable in a fail-safe way
     *
     * In case it fails, reset our connections and state, but keep running
     *
     * @param $method
     */
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

    /**
     * This method shifts issues from the queue to the run-queue
     */
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

    /**
     * Once we sent a notification, remove the related issue froum our run queue
     *
     * @param BemIssue $issue
     */
    public function notifyIssueIsDone(BemIssue $issue)
    {
        $this->running->detach($issue);
        $this->stats->updateRunQueue(
            $this->countRunningProcesses(),
            count($this->queue)
        );
    }

    /**
     * Whether our run queue is full
     *
     * This happens when too many problems are being notified in parallel
     *
     * @return bool
     */
    protected function isRunQueueFull()
    {
        return $this->countRunningProcesses() >= $this->maxParallel;
    }

    /**
     * Count notification processes currently running
     *
     * @return int
     */
    public function countRunningProcesses()
    {
        return $this->running->count();
    }

    protected function shutdownWithSignal($signal, &$func)
    {
        $this->loop()->removeSignal($signal, $func);
        $this->shutdown();
    }

    protected function shutdown()
    {
        $this->isReady = false;
        try {
            Logger::info(
                'Shutting down BEM main runner for %s',
                $this->cellName
            );
            if ($this->health !== null) {
                $this->health->clearPid();
            }
            $this->cell->disconnect();
            if ($this->ido !== null) {
                $this->ido->getDb()->closeConnection();
            }
        } catch (Exception $e) {
            Logger::error(
                'Failed to safely shutdown vSphereDB main runner for %s: %s -> %s, stopping anyways',
                $this->cellName,
                $e->getMessage(),
                $e->getTraceAsString()
            );
        }

        $this->loop()->stop();
    }

    /**
     * Triggers our sending operation
     *
     * This forks a new process, it's outcome will be processed in an asynchronous
     * way
     *
     * @param BemIssue $issue
     * @throws \Icinga\Exception\IcingaException
     */
    protected function sendAndLogEvent(BemIssue $issue)
    {
        $poster = $this->cell->getImpactPoster();
        $this->stats->updateRunQueue(
            $this->countRunningProcesses(),
            count($this->queue)
        );

        $poster->send($issue, $this->loop(), $this);
    }

    /**
     * Lazy-load our main loop
     *
     * @return \React\EventLoop\LoopInterface
     */
    protected function loop()
    {
        if ($this->loop === null) {
            $this->loop = Loop::create();
        }

        return $this->loop;
    }
}
