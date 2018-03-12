<?php

namespace Icinga\Module\Bem;

use Exception;
use Icinga\Application\Logger;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

/**
 * This is an interface to the BMC Impact Poster (msend)
 *
 * All documentation links in this document relative to [bmcdocs] are relative
 * to https://docs.bmc.com/docs/display/public/proactivenet96
 */
class ImpactPoster
{
    /** @var string */
    protected $cellName;

    /** @var string */
    protected $objectClass;

    /** @var string */
    protected $prefixDir;

    public function __construct(
        $cellName,
        $objectClass,
        $prefixDir
    ) {
        $this->cellName = $cellName;
        $this->objectClass = $objectClass;
        $this->prefixDir = $prefixDir;
    }

    public function getVersionString()
    {
        $cmd = implode(' ', [
            $this->getPrefixDir('bin/msend'),
            '-l',
            $this->getPrefixDir(),
            '-z'
        ]);

        return `$cmd`;
        // TODO: msend -l (home) -z
    }

    public function send(BemIssue $issue, LoopInterface $loop = null, MainRunner $runner = null)
    {
        $notification = BemNotification::forIssue($issue);
        $resultHandler = new ImpactPosterResultHandler($issue, $notification, $runner);
        $cmd = $this->getCommandString($notification);

        // Enable one of those to simulate severe problems:
        // $cmd = 'exec sleep 100';
        // $cmd = 'exec /tmp';

        $notification->set('command_line', $cmd);
        $mSend = new Process("exec $cmd");
        if ($loop === null) {
            Logger::info('Creating inner loop');
            $myLoop = Factory::create();
        } else {
            $myLoop = $loop;
        }
        $resultHandler->start($cmd);
        $mSend->start($myLoop);

        $mSend->stdout->on('data', function ($out) use ($resultHandler) {
            $resultHandler->addOutput($out);
        });
        $mSend->stderr->on('data', function ($out) use ($resultHandler) {
            $resultHandler->addOutput($out);
        });

        $timer = $myLoop->addTimer(10, function () use ($mSend) {
            $mSend->terminate();
        });
        $mSend->on('exit', function ($exitCode, $termSignal) use ($resultHandler, $timer, $mSend) {
            $timer->cancel();
            $resultHandler->stop($exitCode, $termSignal, $mSend);
        });
        $mSend->on('error', function (Exception $e) use ($resultHandler, $timer, $mSend) {
            $timer->cancel();
            $resultHandler->addOutput(
                $e->getMessage()
                . "\n"
                . $e->getTraceAsString()
            );
            $resultHandler->stop(255, null, $mSend);
        });

        if ($loop === null) {
            $myLoop->run();
        }

        return $this;
    }

    public function buildParameters(BemNotification $notification)
    {
        // [bmcdocs]/Event+management+common+command+options
        // [bmcdocs]/mposter+and+msend+syntax
        return [
            // Configuration file to use (etc/mclient.conf)
            '-c' => $this->getConfigFilePath(),
            // Connects to this cell, either as defined in mcell.dir and referenced
            // by name - or on specified host and port, with specified key
            '-n' => $this->getCellName(),
            // Sets the event severity value to the Severity specified
            // For example: -r WARNING or -r CRITICAL
            '-r' => $notification->get('severity'),
            // Send an object of this class
            '-a' => $this->getObjectClass(),
            // Adds SlotSetValue settings (format: "slot=value;...")
            // For example,-b "msg='this is a test';mc_tool=computer;"
            '-b' => $this->getSlotSetValueString($notification),
            // milliseconds to wait for message answer (default is 30,000)
            '-w' => 5000,
            '-l' => $this->getPrefixDir(),
            // Verbose. We use this to get the Event ID
            // Output would show a line like: Message #1 - Evtid = 10308244
            '-v',
            // Be "quiet", show no banner. This still respects -v
            '-q',
        ];
    }

    public function getSlotSetValueString(BemNotification $notification)
    {
        $params = array();
        foreach ($notification->getSlotSetValues() as $key => $v) {
            if (preg_match('/^[a-z0-9_]+$/i', $v)) {
                $value = $v;
            } else {
                $value = escapeshellarg($v);
            }
            $params[] = "$key=" . $value;
        }

        return implode(';', $params);
    }

    public function getObjectClass()
    {
        return $this->objectClass;
    }

    /**
     * BMC Cells are event-processing engines
     *
     * msend needs to talk to one of your cells to ship it's events
     * This is either a reference to a cell defined in etc/mcell.dir or host,
     *
     *
     * etc/mcell.dir is a plaintext file with one line per component:
     * <Type> <Name> <EncryptionKey> <IpAddress/Port>
     * <Type> = cell | gateway.portal | gateway.<GWType> | admin
     */
    public function getCellName()
    {
        return $this->cellName;
    }

    public function getPrefixDir($sub = null)
    {
        if ($sub === null) {
            return $this->prefixDir;
        } else {
            return $this->prefixDir . '/' . $sub;
        }
    }

    public function getCommandPath()
    {
        return $this->getPrefixDir('bin/msend');
    }

    public function getConfigFilePath()
    {
        return $this->getPrefixDir('etc/mclient.conf');
    }

    public function getCommandString(BemNotification $notification)
    {
        return implode(' ', $this->getCommandAsArray($notification));
    }

    public function getCommandAsArray(BemNotification $notification)
    {
        return array_merge(
            array($this->getCommandPath()),
            $this->getFlatArguments($notification)
        );
    }

    public function getFlatArguments(BemNotification $notification)
    {
        $flat = array();
        foreach ($this->buildParameters($notification) as $k => $v) {
            if (is_int($k)) {
                $flat[] = $v;
            } else {
                $flat[] = $k;
                if (preg_match('/^[a-z0-9]+$/i', $v)) {
                    $flat[] = $v;
                } else {
                    $flat[] = escapeshellarg($v);
                }
            }
        }

        return $flat;
    }
}
