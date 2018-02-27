<?php

namespace Icinga\Module\Bem\Config;

use Icinga\Application\Config;

class CellConfig
{
    /** @var Config */
    private $config;

    /** @var array */
    private $params;

    /** @var array */
    private $varMap;

    /** @var array */
    private $requiredVars;

    /** @var array */
    private $optionalVars;

    /**
     * BmcCell constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public static function loadByName($name)
    {
        return new static(Config::module('bem', "cells/$name"));
    }

    public function getUsedVarNames()
    {
        return array_merge(
            $this->getRequiredVarNames(),
            $this->getOptionalVarNames()
        );
    }

    public function getRequiredVarNames()
    {
        $this->requireVarMap();
        return array_keys($this->requiredVars);
    }

    public function getOptionalVarNames()
    {
        $this->requireVarMap();
        return array_keys($this->optionalVars);
    }

    /**
     * Returns a key/value array with all properties configured in the [params]
     * config section
     *
     * @return array
     */
    public function getConfiguredParams()
    {
        if ($this->params === null) {
            $this->params = [];
            foreach ($this->config->getSection('params') as $key => $value) {
                $this->params[$key] = $value;
            }
        }

        return $this->params;
    }

    protected function extractVarnamesFromString($string)
    {
        if (preg_match_all('/{([^}]+)}/', $string, $m, PREG_PATTERN_ORDER)) {
            return $m[1];
        } else {
            return [];
        }
    }

    protected function extractAllVarNames()
    {
        $varMap = [];
        $this->requiredVars = [];
        $this->optionalVars = [];
        foreach ($this->getConfiguredParams() as $key => $value) {
            $vars = $this->extractVarnamesFromString($value);
            if (! empty($vars)) {
                $varMap[$key] = $vars;
            }

            foreach ($vars as $var) {
                $parts = preg_split('/\|/', $var);
                if (empty($parts)) {
                    continue;
                }

                if (substr($key, 0, 3) === 'mc_') {
                    $this->addRequiredVar(array_pop($parts));
                }

                foreach ($parts as $part) {
                    $this->addOptionalVar($part);
                }
            }
        }

        foreach (array_keys($this->requiredVars) as $key) {
            unset($this->optionalVars[$key]);
        }

        $this->varMap = $varMap;
    }

    protected function addRequiredVar($var)
    {
        if ($this->stringBeginsLikeVar($var)) {
            $varName = $this->stripModifier($var);
            $this->requiredVars[$varName] = $varName;
        }
    }

    protected function addOptionalVar($var)
    {
        if ($this->stringBeginsLikeVar($var)) {
            $varName = $this->stripModifier($var);
            $this->optionalVars[$varName] = $varName;
        }
    }

    protected function stripModifier($var)
    {
        return preg_replace('/\:.*$/', '', $var);
    }

    protected function stringBeginsLikeVar($string)
    {
        return preg_match('/^(?:host|service)\.vars\./', $string);
    }

    protected function requireVarMap()
    {
        if ($this->varMap === null) {
            $this->extractAllVarNames();
        }
    }

    /**
     * @see Config::get()
     */
    public function get($section, $key, $default = null)
    {
        return $this->config->get($section, $key, $default);
    }
}
