<?php

namespace Icinga\Module\Bem;

class Util
{
    protected static $severityCssClassMap = [
        'UNKNOWN'  => 'state-unknown',
        'OK'       => 'state-ok',
        'INFO'     => 'state-ok',
        'WARNING'  => 'state-warning',
        'MINOR'    => 'state-warning',
        'MAJOR'    => 'state-critical',
        'CRITICAL' => 'state-critical',
        'DOWN'     => 'state-down'
    ];

    /**
     * @return int
     */
    public static function timestampWithMilliseconds()
    {
        $mTime = explode(' ', microtime());

        return (int) round($mTime[0] * 1000) + (int) $mTime[1] * 1000;
    }

    public static function cssClassForSeverity($severity)
    {
        if (array_key_exists($severity, static::$severityCssClassMap)) {
            return static::$severityCssClassMap[$severity];
        } else {
            return 'state-pending';
        }
    }
}
