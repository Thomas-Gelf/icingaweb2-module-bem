<?php

namespace Icinga\Module\Bem\Config;

use Icinga\Application\Config;
use Icinga\Data\ResourceFactory;
use Icinga\Module\Bem\ImpactPoster;

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

    /** @var BlackAndWhitelist */
    private $blackAndWhiteList;

    /** @var \Zend_Db_Adapter_Abstract */
    private $db;

    /**
     * BmcCell constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->blackAndWhiteList = new BlackAndWhitelist($this);
        $this->db = ResourceFactory::create(
            $this->config->get('main', 'db_resource')
        )->getDbAdapter();
    }

    public static function loadByName($name)
    {
        return new static(Config::module('bem', "cells/$name"));
    }

    public function wants($object)
    {
        return $this->blackAndWhiteList->wants($object);
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
            foreach ($this->config->getSection('msend_params') as $key => $value) {
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

    public function fillParams($object)
    {
        $params = $this->getConfiguredParams();
        foreach ($params as $key => $value) {
            $params[$key] = $this->fillPlaceholders($value, $object);
        }

        return $params;
    }

    protected function fillPlaceholders($value, $object)
    {
        return preg_replace_callback(
            '/{([^}]+)}/',
            function ($match) use ($object) {
                return $this->fillPlaceholder($match, $object);
            },
            $value
        );
    }

    protected function stripDomainModifier($value)
    {
        return preg_replace('/\..*/', '', $value);
    }

    protected function fillPlaceholder($match, $object)
    {
        $value = $match[1];
        $parts = explode('|', $value);

        while (! empty($parts)) {
            $part = array_shift($parts);
            $value = $this->evaluatePlaceholder($part, $object);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    protected function evaluatePlaceholder($value, $object)
    {
        if ($value === 'object:getLink') {
            $urlHelper = new IcingaWebUrlHelper($this);

            return $urlHelper->getObjectUrl($object->host_name, $object->service_name);
        }

        $modifiers = explode(':', $value);
        $value = array_shift($modifiers);

        if (preg_match("/^'(.*)'$/", $value, $match)) {
            $value = $match[1];
        } elseif (preg_match('/^\d*$/', $value, $match)) {
            // Number or nothing, keep as is
        } elseif (property_exists($object, $value)) {
            $value = $object->$value;
        } else {
            return null;
        }

        foreach ($modifiers as $modifier) {
            $function = "${modifier}Modifier";
            if (method_exists($this, $function)) {
                $value = $this->$function($value);
            } else {
                $value .= ":$modifier";
            }
        }

        return $value;
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
        return preg_match('/^(?:host|service|object)\.vars\./', $string);
    }

    protected function requireVarMap()
    {
        if ($this->varMap === null) {
            $this->extractAllVarNames();
            foreach ($this->blackAndWhiteList->listFilterColumns() as $column) {
                if ($this->stringBeginsLikeVar($column)) {
                    $this->optionalVars[$column] = $column;
                }
            }
        }
    }

    public function db()
    {
        return $this->db;
    }

    public function getImpactPoster()
    {
        return new ImpactPoster(
            $this->config->get('main', 'cell'),
            $this->get('main', 'object_class', 'ICINGA'),
            $this->get('main', 'prefix_dir', '/usr/local/msend')
        );
    }

    /**
     * @see Config::get()
     */
    public function get($section, $key, $default = null)
    {
        return $this->config->get($section, $key, $default);
    }

    /**
     * @see Config::getSection()
     */
    public function getSection($name)
    {
        return $this->config->getSection($name);
    }
}
