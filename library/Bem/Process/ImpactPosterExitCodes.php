<?php

namespace Icinga\Module\Bem\Process;

class ImpactPosterExitCodes
{
    use PosixExitCodes;
    use SysExitCodes;

    /**
     * [bmcdocs] = https://docs.bmc.com/docs/display/public/proactivenet96
     * @var array
     */
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

    public function getExitCodeDescription($code)
    {
        if (in_array($code, static::$returnCodes)) {
            return static::$returnCodes[$code];
        } elseif ($this->isPosixExitCode($code)) {
            return $this->getPosixExitCodeDescription($code);
        } elseif ($this->isSysExitCode($code)) {
            return $this->getSysExitCodeDescription($code);
        } else {
            return sprintf('Exited with code %d', $code);
        }
    }
}
