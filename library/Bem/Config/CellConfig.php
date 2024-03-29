<?php

namespace Icinga\Module\Bem\Config;

use Icinga\Application\Config;
use Icinga\Data\Filter\Filter;
use Icinga\Data\ResourceFactory;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Bem\Hook\PlaceholderHook;
use Icinga\Module\Bem\ImpactPoster;
use Icinga\Web\Hook;

class CellConfig
{
    /** @var string */
    private $name;

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

    /** @var \Zend_Db_Adapter_Abstract */
    private $otherDb;

    /** @var ImpactPoster */
    private $impactPoster;

    private $configCheckSum;

    private $maps;

    /** @var PlaceholderHook[] */
    private $placeHolderHooks;

    /**
     * BmcCell constructor.
     * @param Config $config
     * @param string $name
     */
    public function __construct(Config $config, $name)
    {
        $this->name = $name;
        $this->config = $config;
        // TODO: Fail with missing config.
        $this->triggerFreshConfig();
    }

    public function hasFailOver()
    {
        return $this->config->get('main', 'other_db_resource') !== null;
    }

    public function getRole()
    {
        return $this->config->get('main', 'role', 'master');
    }

    public function shouldBeMaster()
    {
        return $this->config->get('main', 'role', 'master') === 'master';
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function getMaxParallelRunners()
    {
        return (int) $this->config->get('main', 'max_parallel_runners', 3);
    }

    /**
     * @throws \Icinga\Exception\NotReadableError
     */
    protected function refreshConfig()
    {
        $this->config = Config::fromIni($this->config->getConfigFile());
        $this->triggerFreshConfig();
    }

    /**
     * @return bool
     * @throws \Icinga\Exception\NotReadableError
     */
    public function checkForFreshConfig()
    {
        if ($this->configHasBeenChangedOnDisk()) {
            $this->refreshConfig();
            return true;
        }

        return false;
    }

    protected function loadMaps()
    {
        $this->maps = [];
        foreach (Config::module('bem', 'maps') as $name => $section) {
            $this->maps[$name] = new Map($section);
        }
    }

    /**
     * @param $object
     * @return string
     * @throws ConfigurationError
     */
    public function calculateSeverityForIcingaObject($object)
    {
        $state = $object->state;

        foreach ($this->config as $title => $section) {
            if (preg_match('/^modifier\./', $title)) {
                if ($section->get('modifier') !== 'map') {
                    throw new ConfigurationError(
                        'Unknown modifier: %s',
                        $section->get('modifier')
                    );
                }

                if ($filter = $section->get('filter')) {
                    $filter = Filter::fromQueryString($filter);
                    if ($filter->matches($object)) {
                        $map = $this->requireMap($section->get('map_name'));
                        $state = $map->map($state);
                    }
                }
            }
        }

        return $state;
    }

    /**
     * @param $name
     * @return Map
     * @throws ConfigurationError
     */
    protected function requireMap($name)
    {
        if (! array_key_exists($name, $this->maps)) {
            throw new ConfigurationError(
                'Required map does not exist: %s',
                $name
            );
        }

        return $this->maps[$name];
    }

    protected function configHasBeenChangedOnDisk()
    {
        return $this->getConfigChecksumFromDisk() !== $this->configCheckSum;
    }

    protected function getConfigChecksumFromDisk()
    {
        $fileName = $this->config->getConfigFile();

        return sha1(file_get_contents($fileName));
    }

    protected function triggerFreshConfig()
    {
        $this->configCheckSum = $this->getConfigChecksumFromDisk();

        $this->blackAndWhiteList = new BlackAndWhitelist($this);
        $this->loadMaps();

        $this->db = null;
        $this->otherDb = null;
        $this->impactPoster = null;
        $this->params = null;
        $this->optionalVars = null;
        $this->requiredVars = null;
        $this->varMap = null;

        $this->db = ResourceFactory::create(
            $this->config->get('main', 'db_resource')
        )->getDbAdapter();

        if ($other = $this->config->get('main', 'other_db_resource')) {
            $this->otherDb = ResourceFactory::create($other)->getDbAdapter();
        }
    }

    public static function loadByName($name)
    {
        return new static(Config::module('bem', "cells/$name"), $name);
    }

    public function wantsIcingaObject($object)
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

    /**
     * @return PlaceholderHook[]
     */
    protected function getPlaceHolderHooks()
    {
        if ($this->placeHolderHooks === null) {
            /** @var PlaceholderHook[] $hooks */
            $hooks = Hook::all('Bem\\Placeholder');

            $enum = [];
            foreach ($hooks as $hook) {
                $enum[$hook->getPlaceholderName()] = $hook;
            }

            $this->placeHolderHooks = $enum;
        }

        return $this->placeHolderHooks;
    }

    protected function hasPlaceHolderHook($name)
    {
        $hooks = $this->getPlaceHolderHooks();

        return array_key_exists($name, $hooks);
    }

    protected function evaluatePlaceHolderHook($name, $icingaObject)
    {
        $hooks = $this->getPlaceHolderHooks();

        if (array_key_exists($name, $hooks)) {
            return $hooks[$name]->evaluate($icingaObject);
        } else {
            return null;
        }
    }


    protected function evaluatePlaceholder($value, $object)
    {
        if ($this->hasPlaceHolderHook($value)) {
            return $this->evaluatePlaceHolderHook($value, $object);
        }

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
            $function = "{$modifier}Modifier";
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

    public function disconnect()
    {
        if ($this->db !== null) {
            $this->db->closeConnection();
        }
        if ($this->otherDb !== null) {
            $this->otherDb->closeConnection();
        }
    }

    public function db()
    {
        return $this->db;
    }

    public function otherDb()
    {
        return $this->otherDb;
    }

    public function getImpactPoster()
    {
        if ($this->impactPoster === null) {
            $this->impactPoster = new ImpactPoster(
                $this->config->get('main', 'cell'),
                $this->get('main', 'object_class', 'ICINGA'),
                $this->get('main', 'prefix_dir', '/usr/local/msend')
            );
        }

        return $this->impactPoster;
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
