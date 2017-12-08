<?php

namespace Icinga\Module\Bem\Process;

use Icinga\Exception\IcingaException;

trait SysExitCodes
{
    protected static $sysexitCodeConstants = [
        64 => 'EX_USAGE',
        65 => 'EX_DATAERR',
        66 => 'EX_NOINPUT',
        67 => 'EX_NOUSER',
        68 => 'EX_NOHOST',
        69 => 'EX_UNAVAILABLE',
        70 => 'EX_SOFTWARE',
        71 => 'EX_OSERR',
        72 => 'EX_OSFILE',
        73 => 'EX_CANTCREAT',
        74 => 'EX_IOERR',
        75 => 'EX_TEMPFAIL',
        76 => 'EX_PROTOCOL',
        77 => 'EX_NOPERM',
        78 => 'EX_CONFIG',
    ];

    protected static $sysexitCodeTitles = [
        64 => 'command line usage error',
        65 => 'data format error',
        66 => 'cannot open input',
        67 => 'addressee unknown',
        68 => 'host name unknown',
        69 => 'service unavailable',
        70 => 'internal software error',
        71 => 'system error (e.g., can\'t fork)',
        72 => 'critical OS file missing',
        73 => 'can\'t create (user) output file',
        74 => 'input/output error',
        75 => 'temp failure; user is invited to retry',
        76 => 'remote error in protocol',
        77 => 'permission denied',
        78 => 'configuration error',
    ];

    protected function getSysExitCodeConstantName($code)
    {
        $this->assertSysExitCode($code);

        return static::$sysexitCodeConstants[(int) $code];
    }

    protected function getSysExitCodeTitle($code)
    {
        $this->assertSysExitCode($code);

        return static::$sysexitCodeTitles[(int) $code];
    }

    protected function getSysExitCodeDescription($code)
    {
        $this->assertSysExitCode($code);

        return $this->getSysExitCodeDescriptions()[(int) $code];
    }

    protected function assertSysExitCode($code)
    {
        if (! is_int($code) && ! ctype_digit($code)) {
            throw new IcingaException(
                '"%s" is not numeric and can therefore not be an exit code',
                $code
            );
        }

        if ((int) $code < 64 || (int) $code > 78) {
            throw new IcingaException(
                '"%s" is not in the sysexits.h range of exit codes',
                $code
            );
        }
    }

    protected function isSysExitCode($code)
    {
        if (! is_int($code) && ! ctype_digit($code)) {
            return false;
        }

        return (int) $code >= 64 && (int) $code <= 78;
    }

    protected function getSysExitCodeDescriptions()
    {
        return [
            64 => 'The command was used incorrectly, e.g., with the wrong number'
                . ' of arguments, a bad flag, a bad syntax in a parameter, or'
                . ' whatever.',
            65 => 'The input data was incorrect in some way. This should only'
                . ' be used for user\'s data & not system files.',
            66 => 'An input file (not a system file) did not exist or was not'
                . ' readable.  This could also include errors like "No message"'
                . ' to a mailer (if it cared to catch it).',
            67 => 'The user specified did not exist.  This might be used for'
                . ' mail addresses or remote logins.',
            68 => 'The host specified did not exist.  This is used in mail'
                . ' addresses or network requests.',
            69 => 'A service is unavailable.  This can occur if a support program'
                . ' or file does not exist.  This can also be used as a catchall'
                . ' message when something you wanted to do doesn\'t work, but'
                . ' you don\'t know why.',
            70 => 'An internal software error has been detected. This should be'
                . ' limited to non-operating system related errors as possible.',
            71 => 'An operating system error has been detected. This is intended'
                . ' to be used for such things as "cannot fork", "cannot create'
                . ' pipe", or the like.  It includes things like getuid returning'
                . ' a user that does not exist in the passwd file.',
            72 => 'Some system file (e.g., /etc/passwd, /etc/utmp, etc.) does'
                . ' not exist, cannot be opened, or has some sort of error'
                . ' (e.g., syntax error).',
            73 => 'A (user specified) output file cannot be created.',
            74 => 'An error occurred while doing I/O on some file.',
            75 => 'temporary failure, indicating something that is not really'
                . ' an error.  In sendmail, this means',
            76 => 'the remote system returned something that was "not possible"'
                . ' during a protocol exchange. that a mailer (e.g.) could not'
                . ' create a connection, and the request should be reattempted'
                . ' later.',
            77 => 'You did not have sufficient permission to perform the operation. '
                . ' This is not intended for file system problems, which should use'
                . ' NOINPUT or CANTCREAT, but rather for higher level permissions.',
            78 => 'Something was found in an unconfigured or misconfigured state.',
        ];
    }
}
