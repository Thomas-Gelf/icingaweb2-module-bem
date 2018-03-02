<?php

namespace Icinga\Module\Bem\Config;

use Icinga\Data\ConfigObject;

class Map
{
    protected $keys = [];

    public function __construct(ConfigObject $config)
    {
        foreach ($config as $key => $value) {
            $this->keys[$key] = $value;
        }
    }

    public function map($value)
    {
        if (array_key_exists($value, $this->keys)) {
            return $this->keys[$value];
        } else {
            return $value;
        }
    }
}
