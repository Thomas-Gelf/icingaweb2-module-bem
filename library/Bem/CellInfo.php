<?php

namespace Icinga\Module\Bem;

use Icinga\Module\Bem\Config\CellConfig;

class CellInfo
{
    /** @var object */
    protected $info;

    /** @var CellConfig */
    protected $cell;

    /**
     * CellInfo constructor.
     * @param CellConfig $cell
     * @param \stdClass|null $info
     */
    public function __construct(CellConfig $cell, $info = null)
    {
        $this->cell = $cell;
        $this->setInfo($info);
    }

    public function setInfo($info)
    {
        if (empty($info)) {
            $this->info = $this->createEmptyInfo();
        } else {
            $this->info = $info;
        }

        return $this;
    }

    public function isRunning()
    {
        return $this->getPid() !== null && ! $this->isOutdated();
    }

    public function getPid()
    {
        return $this->info->pid;
    }

    public function getUsername()
    {
        return $this->info->username;
    }

    public function getFqdn()
    {
        return $this->info->fqdn;
    }

    public function getLastUpdate()
    {
        return $this->info->ts_last_update;
    }

    public function getLastModification()
    {
        return $this->info->ts_last_modification;
    }

    public function getQueueSize()
    {
        return $this->info->queue_size;
    }

    public function getMaxProcessCount()
    {
        return $this->info->max_parallel_processes;
    }

    public function getRunningProcessCount()
    {
        return $this->info->running_processes;
    }

    public function getPhpVersion()
    {
        return $this->info->php_version;
    }

    public function isOutdated($seconds = 5)
    {
        return (
            Util::timestampWithMilliseconds() - $this->info->ts_last_update
        ) > $seconds * 1000;
    }

    public function isMaster()
    {
        return $this->info->is_master === 'y';
    }

    public function isStandby()
    {
        return ! $this->isMaster();
    }

    protected function createEmptyInfo()
    {
        return (object) [
            'event_counter'          => 0,
            'running_processes'      => 0,
            'queue_size'             => 0,
            'pid'                    => null,
            'fqdn'                   => null,
            'username'               => null,
            'php_version'            => null,
            'is_master'              => null,
            'max_parallel_processes' => 0,
            'ts_last_modification'   => 0
        ];
    }
}
