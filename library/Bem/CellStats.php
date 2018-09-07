<?php

namespace Icinga\Module\Bem;

use Icinga\Application\Logger;
use Icinga\Module\Bem\Config\CellConfig;

class CellStats
{
    /** @var array */
    protected $stats;

    /** @var array */
    protected $storedStats;

    /** @var CellConfig */
    protected $cell;

    protected $readOnly;

    public function __construct(CellConfig $cell, $readOnly = false)
    {
        $this->cell = $cell;
        $this->loadStatsFromDb();
        $this->readOnly = $readOnly;
    }

    public static function exist(CellConfig $cell)
    {
        $db = $cell->db();

        return $cell->getName() === $db->fetchOne(
            $db->select()
                ->from('bem_cell_stats', 'cell_name')
                ->where('cell_name = ?', $cell->getName())
        );
    }

    public function getEventCounter()
    {
        return $this->stats['event_counter'];
    }

    public function get($key)
    {
        if (array_key_exists($key, $this->stats)) {
            return $this->stats[$key];
        } else {
            return null;
        }
    }

    public function updateRunQueue($runningProcesses, $queueSize, $incrementEvents = true)
    {
        if ($incrementEvents) {
            $this->stats['event_counter']++;
        }
        $this->stats['running_processes'] = $runningProcesses;
        $this->stats['queue_size']        = $queueSize;

        return $this;
    }

    public function isOutdated()
    {
        return (
            Util::timestampWithMilliseconds()
            - $this->stats['ts_last_update']
        ) > 90 * 1000;
    }

    public function updateStats($force = false)
    {
        if ($this->readOnly) {
            return;
        }

        if ($this->storedStats === $this->stats) {
            if ($force) {
                Logger::debug('Forced stats update');
            } else {
                return;
            }
        } else {
            $this->stats['ts_last_modification'] = Util::timestampWithMilliseconds();
        }

        $update = array_key_exists('ts_last_update', $this->stats);
        $this->stats['ts_last_update'] = Util::timestampWithMilliseconds();

        if ($update) {
            try {
                $this->updateDbStats();
            } catch (\Exception $e) {
                try {
                    $this->loadStatsFromDb();
                } catch (\Exception $e2) {
                    throw $e;
                }
            }
        } else {
            $this->insertDbStats();
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
                'ts_last_update',
            ])->where('cell_name = ?', $this->cell->getName())
        );

        if (empty($stats)) {
            $this->setEmptyStats();
            $this->updateStats(true);
        } else {
            $this->setLoadedStats($stats);
            $this->updateStats();
        }
    }

    public static function fetch(CellConfig $cell)
    {
        $db = $cell->db();
        $result = $db->fetchRow(
            $db->select()->from('bem_cell_stats')
                ->where('cell_name = ?', $cell->getName())
        );

        if ($result === false) {
            return (object) [
                'event_counter'          => 0,
                'running_processes'      => 0,
                'queue_size'             => 0,
                'pid'                    => null,
                'fqdn'                   => null,
                'username'               => null,
                'php_version'            => null,
                'max_parallel_processes' => 0,
                'ts_last_modification'   => 0
            ];
        }

        return $result;
    }

    protected function setLoadedStats($stats)
    {
        $this->stats = [];
        foreach ($stats as $key => $value) {
            $this->stats[$key] = (int) $value;
        }
        $this->storedStats = $this->stats;
        $this->stats['max_parallel_processes'] = $this->cell->getMaxParallelRunners();
    }

    protected function setEmptyStats()
    {
        $this->stats = [
            'event_counter'          => 0,
            'running_processes'      => 0,
            'queue_size'             => 0,
            'max_parallel_processes' => $this->cell->getMaxParallelRunners(),
            'ts_last_modification'   => 0
        ];
    }

    protected function updateDbStats()
    {
        Logger::debug('Updating DB stats');
        $db = $this->cell->db();
        $res = $db->update(
            'bem_cell_stats',
            $this->stats,
            $db->quoteInto('cell_name = ?', $this->cell->getName())
        );

        if ($res === 1) {
            $this->storedStats = $this->stats;
        } else {
            Logger::info('Running useless DB stats update query');
            $this->loadStatsFromDb();
        }
    }

    protected function insertDbStats()
    {
        Logger::info('Filling initial DB stats for %s', $this->cell->getName());

        $this->cell->db()->insert(
            'bem_cell_stats',
            $this->stats + ['cell_name' => $this->cell->getName()]
        );
        $this->storedStats = $this->stats;
    }
}
