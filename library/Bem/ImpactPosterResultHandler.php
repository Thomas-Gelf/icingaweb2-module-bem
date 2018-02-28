<?php

namespace Icinga\Module\Bem;

class ImpactPosterResultHandler
{
    /** @var BemNotification */
    protected $notification;

    /** @var string */
    private $outputBuffer = '';

    private $startTime;

    public function __construct(BemNotification $notification)
    {
        $this->notification = $notification;
    }

    public function start($commandLine)
    {
        $this->startTime = Util::timestampWithMilliseconds();
        $this->notification->set('command_line', $commandLine);
        $this->notification->set('ts_notification', $this->startTime);
    }

    public function stop($exitCode, $termSignal)
    {
        $n = $this->notification;
        $n->set('duration_ms', $this->startTime - Util::timestampWithMilliseconds());
        if ($exitCode === null) {
            if ($termSignal === null) {
                $n->set('exit_code', 255);
            } else {
                $n->set('exit_code', 128 + $termSignal);
            }
        } else {
            $n->set('exit_code', (int) $exitCode);
        }

        $n->storeToLog();
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
