<?php

namespace Icinga\Module\Bem\Hook;

abstract class PlaceholderHook
{
    /**
     * The name of your Placeholder
     *
     * Should look like a method name, like getCmdbLink
     *
     * @return string
     */
    abstract public function getPlaceholderName();

    /**
     * Extend this Hook to provide custom Placeholders
     *
     * The given object has the following properties:
     *
     *   id => int, object_id
     *   object_type => host|service
     *   host_id => int, the host's object_id, on services only
     *   host_name => string
     *   service_name => string
     *   state_type => HARD|SOFT
     *   state => OK|WARNING|CRITICAL|UNKNOWN|UP|DOWN|UNREACHABLE
     *   is_acknowledged => 0|1
     *   is_in_downtime => 0|1
     *   output => string
     *   host.vars.xxx => string
     *   service.vars.xxx => string
     *
     * @param object $icingaObject
     * @return string|null
     */
    abstract public function evaluate($icingaObject);
}
