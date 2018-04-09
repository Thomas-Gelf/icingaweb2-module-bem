<?php

namespace Icinga\Module\Bem;

use React\ChildProcess\Process;

class ImpactPosterResultHandler
{
    /** @var BemNotification */
    protected $notification;

    /** @var BemIssue */
    protected $issue;

    /** @var string */
    private $outputBuffer = '';

    private $startTime;

    /** @var MainRunner */
    private $runner;

    public function __construct(
        BemIssue $issue,
        BemNotification $notification = null,
        MainRunner $runner = null
    ) {
        $this->issue = $issue;
        if ($notification === null) {
            $this->notification = BemNotification::forIssue($issue);
        } else {
            $this->notification = $notification;
        }

        $this->runner = $runner;
    }

    /**
     * @param $commandLine
     * @throws \Icinga\Exception\IcingaException
     */
    public function start($commandLine)
    {
        $this->startTime = Util::timestampWithMilliseconds();
        $this->notification
            ->set('command_line', $commandLine)
            ->set('system_user', posix_getpwnam(posix_getuid()))
            ->set('system_host_name', gethostname());
    }

    /**
     * @param $exitCode
     * @param $termSignal
     * @param Process $process
     * @throws \Icinga\Exception\IcingaException
     * @throws \Zend_Db_Adapter_Exception
     */
    public function stop($exitCode, $termSignal, Process $process)
    {
        $n = $this->notification;

        $n->set('pid', $process->getPid());
        $n->set('ts_notification', $this->startTime);
        $n->set('duration_ms', Util::timestampWithMilliseconds() - $this->startTime);
        if ($exitCode === null) {
            if ($termSignal === null) {
                $n->set('exit_code', 255);
            } else {
                $n->set('exit_code', 128 + $termSignal);
            }
        } else {
            $n->set('exit_code', (int) $exitCode);
        }
        $n->set('output', $this->outputBuffer);
        $n->set('bem_event_id', $this->extractEventId());
        $n->storeToLog();
        $this->updateIssue();
        if ($this->runner !== null) {
            $this->runner->notifyIssueIsDone($this->issue);
        }
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function updateIssue()
    {
        $i = $this->issue;
        $count = $i->get('cnt_notifications');
        $i->set('ts_last_notification', $this->startTime);
        if ((int) $count === 0) {
            $i->set('ts_first_notification', $this->startTime);
        }

        $i->set('cnt_notifications', $count + 1);
        $i->set('ts_next_notification', $this->notification->calculateNextNotification());
        $i->store();
    }

    public function extractEventId()
    {
        // TODO: figure out how whether we could benefit from this while streaming
        // to msend's STDIN
        if (preg_match('/Message #(\d+) - Evtid = (\d+)/', $this->outputBuffer, $match)) {
            return $match[2];
        } else {
            return null;
        }
    }

    /**
     * @param string $output
     * @return $this
     */
    public function addOutput($output)
    {
        $this->outputBuffer .= $output;

        return $this;
    }
}
