<?php

namespace Icinga\Module\Bem\Process;

use Icinga\Exception\IcingaException;

trait PosixExitCodes
{
    protected static $posixExitCodeDescriptions = [
        0 => 'Success',
        // Generic exit codes:
        126 => 'Permission problem or command is not an executable',
        127 => 'Command not found',
        128 => 'Invalid argument to exit',

        // 128 + X -> X = signal
        129 => 'Terminated. Got SIGHUP (Hangup)',
        130 => 'Terminated. Got SIGINT (Terminal interrupt signal, Control-C)',
        131 => 'Terminated (core dump). Got SIGQUIT (Terminal interrupt signal, Control-C)',
        255 => 'Exit status out of range',
    ];

    protected function getPosixExitCodeDescription($code)
    {
        $this->assertPosixExitCode($code);

        return static::$posixExitCodeDescriptions[(int) $code];
    }

    protected function assertPosixExitCode($code)
    {
        if (! is_int($code) && ! ctype_digit($code)) {
            throw new IcingaException(
                '"%s" is not numeric and can therefore not be an exit code',
                $code
            );
        }

        if ((int) $code < 64 || (int) $code > 78) {
            throw new IcingaException(
                '"%s" is not a well known POSIX exit code',
                $code
            );
        }
    }

    protected function isPosixExitCode($code)
    {
        if (! is_int($code) && ! ctype_digit($code)) {
            return false;
        }

        return array_key_exists((int) $code, static::$posixExitCodeDescriptions);
    }
}
