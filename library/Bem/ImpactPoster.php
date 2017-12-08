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
    /* return codes */
    protected static $returnCodes = [
        // [bmcdocs]/Event+management+return+codes
        1 => 'Bad usage (command includes nonexistent options or an invalid combination of options and arguments)',
        10 => 'Initialization failure',
        11 => 'Trace initialization failed',
        12 => 'Configuration initialization failed',
        13 => 'Outbound communication setup failed',
        14 => 'Inbound communication setup failed',
        15 => 'Message handling initialization failed',
        16 => 'Persistency setup failed',
        17 => 'Port range limitation failed',
        20 => 'Connection to cell failed',
        25 => 'Memory fault',
        26 => 'Command failed',
        27 => 'Syntax error',
        28 => 'Invalid answer received',

        // [bmcdocs]/mposter+and+msend+return+codes
        31 => 'Failed to initialize language module',
        32 => 'Failed to launch or to connect to the server',
    ];

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

    public function send(Event $event, $then, LoopInterface $loop = null)
    {
        $event->resetRunStatus();
        $cmd = 'exec ' . $this->getCommandString($event);
        $cmd = 'exec sleep 100';
        // $cmd = 'exec /tmp';

        $event->setLastCmdLine($cmd);
        $mSend = new Process($cmd);
        if ($loop === null) {
            Logger::info('Creating inner loop');
            $myLoop = Factory::create();
        } else {
            $myLoop = $loop;
        }
        $mSend->start($myLoop);

        $mSend->stdout->on('data', function ($out) use ($event) {
            $event->addOutput($out);
        });
        $mSend->stderr->on('data', function ($out) use ($event) {
            $event->addOutput($out);
        });

        $timer = $myLoop->addTimer(10, function () use ($mSend) {
            $mSend->terminate();
        });
        $mSend->on('exit', function ($exitCode, $termSignal) use ($then, $event, $timer) {
            $timer->cancel();
            if ($exitCode === null) {
                if ($termSignal === null) {
                    $event->setLastExitCode(255);
                } else {
                    $event->setLastExitCode(128 + $termSignal);
                }
            } else {
                $event->setLastExitCode((int) $exitCode);
            }

            $then($event);
        });
        $mSend->on('error', function (Exception $e) use ($then, $event) {
            $event->addOutput(
                $e->getMessage()
                . "\n"
                . $e->getTraceAsString()
            );
            $event->setLastExitCode(255);
            $then($event);
        });

        if ($loop === null) {
            $myLoop->run();
        }

        return $this;
    }

    public function getParameters(Event $event)
    {
        // [bmcdocs]/Event+management+common+command+options
        // [bmcdocs]/mposter+and+msend+syntax
        return array(
            // Configuration file to use (etc/mclient.conf)
            '-c' => $this->getConfigFilePath(),
            // Connects to this cell, either as defined in mcell.dir and referenced
            // by name - or on specified host and port, with specified key
            '-n' => $this->getCellName(),
            // Sets the event severity value to the Severity specified
            // For example: -r WARNING or -r CRITICAL
            '-r' => $event->getSeverity(),
            // Send an object of this class
            '-a' => $this->getObjectClass(),
            // Adds SlotSetValue settings (format: "slot=value;...")
            // For example,-b "msg='this is a test';mc_tool=computer;"
            '-b' => $this->getEscapedSlotSetValues($event),
            // milliseconds to wait for message answer (default is 30,000)
            '-w' => 5000,
            // Verbose. We use this to get the Event ID
            // Output would show a line like: Message #1 - Evtid = 10308244
            '-v',
            // Be "quiet", show no banner. This still respects -v
            '-q'
        );
    }

    public function getEscapedSlotSetValues(Event $event)
    {
        return escapeshellarg($event->getEscapedParameters());
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
        return escapeshellarg($this->getPrefixDir('bin/msend'));
    }

    public function getConfigFilePath()
    {
        return escapeshellarg($this->getPrefixDir('etc/mclient.conf'));
    }

    public function getCommandString(Event $event)
    {
        return implode(' ', $this->getCommandAsArray($event));
    }

    public function getFlatArguments(Event $event)
    {
        $flat = array();
        foreach ($this->getParameters($event) as $k => $v) {
            if (! is_int($k)) {
                $flat[] = $k;
            }
            $flat[] = $v;
        }

        return $flat;
    }

    public function getCommandAsArray(Event $event)
    {
        return array_merge(
            array($this->getCommandPath()),
            $this->getFlatArguments($event)
        );
    }
}
