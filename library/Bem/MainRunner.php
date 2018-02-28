<?php

namespace Icinga\Module\Bem;

use Icinga\Application\Logger;
use Icinga\Module\Bem\Config\CellConfig;
use React\EventLoop\Factory as Loop;
use SplObjectStorage;

class MainRunner
{
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

    protected $eventCounter = 0;

    /** @var BemIssues */
    protected $issues;

    protected $stats = [
        'ts_last_modification' => 0,
    ];

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

        $loop->addPeriodicTimer(0.5, function () {
            $this->fillQueue();
        });
        $loop->addPeriodicTimer(0.1, function () {
            $this->runQueue();
        });
        $loop->addPeriodicTimer(1, function () {
            $this->updateStats();
        });
        $loop->addPeriodicTimer(60, function () {
            $this->updateDbStats();
        });
        $loop->addPeriodicTimer(30, function () {
            if ($this->cell->checkForFreshConfig()) {
                $this->reset();
            }
        });
        $loop->run();
    }

    protected function reset()
    {
        $this->maxParallel = $this->cell->get('main', 'max_parallel_runners', 3);
        $this->issues = new BemIssues($this->cell->db());
        $this->loadStatsFromDb();
    }

    public function runOnceFor($host, $service = null)
    {
        $object = IdoDb::fromMonitoringModule()->getStateRowFor($host, $service);
        $this->sendAndLogEvent(
            BemNotification::forIcingaObject($object, $this->cell)
        );
    }

    protected function updateStats()
    {
        $stats = [
            'event_counter'          => $this->eventCounter,
            'max_parallel_processes' => $this->maxParallel,
            'running_processes'      => $this->running->count(),
            'queue_size'             => count($this->queue),
            'ts_last_modification'   => $this->stats['ts_last_modification'],
        ];
        Logger::info(
            '%d/%d running, %d in queue',
            $stats['running_processes'],
            $stats['max_parallel_processes'],
            $stats['queue_size']
        );

        if ($stats !== $this->stats) {
            $stats['ts_last_modification'] = Util::timestampWithMilliseconds();
            $this->stats = $stats;
            if (array_key_exists('ts_last_update', $this->stats)) {
                $this->updateDbStats();
            } else {
                $this->insertDbStats();
            }
        }
    }

    protected function loadStatsFromDb()
    {
        $db = $this->cell->db();
        $stats = $db->fetchRow(
            $db->select()->from('bem_cell_stats', [
                'event_counter',
                'max_parallel_processes',
                'running_processes',
                'queue_size',
                'ts_last_modification',
            ])->where('cell_name = ?', $this->cellName)
        );

        if (! empty($stats)) {
            $this->stats = (array) $stats;
            $this->eventCounter = $this->stats['event_counter'];
        }

        $this->updateStats();
    }

    protected function updateDbStats()
    {
        $db = $this->cell->db();
        $db->update(
            'bem_cell_stats',
            $this->stats + [
                'ts_last_update' => Util::timestampWithMilliseconds()
            ],
            $db->quoteInto('cell_name = ?', $this->cellName)
        );
    }

    protected function insertDbStats()
    {
        $db = $this->cell->db();
        $db->insert(
            'bem_cell_stats',
            $this->stats + [
                'ts_last_update' => Util::timestampWithMilliseconds(),
                'cell_name'      => $this->cellName,
            ]
        );
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

    protected function runQueue()
    {
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

    protected function sendAndLogEvent(BemNotification $notification)
    {
        $poster = $this->cell->getImpactPoster();
        $poster->send($notification, $this->loop);
    }

    protected function issues()
    {

    }

    protected function loop()
    {
        if ($this->loop === null) {
            $this->loop = Loop::create();
        }

        return $this->loop;
    }
}
