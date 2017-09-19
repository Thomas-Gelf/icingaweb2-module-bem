<?php

namespace Icinga\Module\Bem;

use Icinga\Exception\ProgrammingError;

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
        0 => 'Success',
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

        // Alternatively, generic exit codes:
        126 => 'Permission problem or command is not an executable',
        127 => 'Command not found',
        128 => 'Invalid argument to exit',
        130 => 'Got Control-C',
        255 => 'Exit status out of range',
    ];

    /** @var Event */
    protected $event;

    /** @var string */
    protected $cellName;

    /** @var string */
    protected $objectClass;

    /** @var string */
    protected $prefixDir;

    protected $lastOutput;

    public function __construct(
        $cellName,
        $objectClass,
        $prefixDir
    ) {
        $this->cellName = $cellName;
        $this->objectClass = $objectClass;
        $this->prefixDir = $prefixDir;
    }

    public function setEvent(Event $event)
    {
        $this->event = $event;
        return $this;
    }

    public function getVersionString()
    {
        // TODO: msend -l (home) -z
    }

    public function getEvent()
    {
        if ($this->event === null) {
            throw new ProgrammingError(
                'Unable to access an event before one has been set'
            );
        }

        return $this->event;
    }

    public function send()
    {
        $cmd = $this->getCommandString();

        // TODO: exec in a clean way, failsafe, read errors, kill with timeout
        $this->lastOutput = `$cmd 2>&1`;
        return $this;
    }

    public function getLastOutput()
    {
        return $this->lastOutput;
    }

    public function getLastExitCode()
    {
        // Just for now
        return 0;
    }

    public function getParameters()
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
            '-r' => $this->getEvent()->getSeverity(),
            // Send an object of this class
            '-a' => $this->getObjectClass(),
            // Adds SlotSetValue settings (format: "slot=value;...")
            // For example,-b "msg='this is a test';mc_tool=computer;"
            '-b' => $this->getEscapedSlotSetValues(),
            // milliseconds to wait for message answer (default is 30,000)
            '-w' => 5000,
            // Verbose. We use this to get the Event ID
            // Output would show a line like: Message #1 - Evtid = 10308244
            '-v',
            // Be "quiet", show no banner. This still respects -v
            '-q'
        );
    }

    public function getLastId()
    {
        $output = $this->getLastOutput();
        if (null === $output) {
            return null;
        }

        // TODO: figure out how whether we could benefit from this while streaming
        // to msend's STDIN
        if (preg_match('/Message #(\d+) - Evtid = (\d+)/', $output, $match)) {
            return $match[2];
        } else {
            return null;
        }
    }

    public function getEscapedSlotSetValues()
    {
        return escapeshellarg($this->getEvent()->getEscapedParameters());
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

    public function getCommandString()
    {
        return implode(' ', $this->getCommandAsArray());
    }

    public function getFlatArguments()
    {
        $flat = array();
        foreach ($this->getParameters() as $k => $v) {
            if (! is_int($k)) {
                $flat[] = $k;
            }
            $flat[] = $v;
        }

        return $flat;
    }

    public function getCommandAsArray()
    {
        return array_merge(
            array($this->getCommandPath()),
            $this->getFlatArguments()
        );
    }
}
