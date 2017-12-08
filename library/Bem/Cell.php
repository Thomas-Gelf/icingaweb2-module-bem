<?php

namespace Icinga\Module\Bem;

use Icinga\Application\Config as Conf;
use Zend_Db_Select as DbSelect;
use Icinga\Data\ResourceFactory;

class Cell
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

    /** @var ImpactPoster */
    private $msend;

    /** @var Notifications */
    private $notifications;

    /** @var IdoDb */
    private $ido;

    /**
     * BmcCell constructor.
     * @param Conf $config
     */
    public function __construct(Conf $config)
    {
        $this->config = $config;
    }

    /**
     * @return IdoDb
     */
    public function getIdo()
    {
        if ($this->ido === null) {
            $this->ido = IdoDb::fromMonitoringModule();
        }

        return $this->ido;
    }

    public function notifications()
    {
        if ($this->notifications === null) {
            $this->notifications = new Notifications(
                ResourceFactory::create(
                    $this->config->get('main', 'db_resource')
                )->getDbAdapter()
            );
        }

        return $this->notifications;
    }

    /**
     * @return DbSelect
     */
    public function selectProblemEvents()
    {
        $db = $this->getIdo()->getDb();
        $helper = new QueryHelper($db);

        return $this->addVarsToQuery($helper, $helper->selectProblemsForBmc());
    }

    /**
     * @return \stdClass[]
     */
    public function fetchProblemEvents()
    {
        return $this->getIdo()->getDb()->fetchAll($this->selectProblemEvents());
    }

    /**
     * @return DbSelect
     */
    public function selectEvents()
    {
        $db = $this->getIdo()->getDb();
        $helper = new QueryHelper($db);

        return $this->addVarsToQuery($helper, $helper->selectBmcObjects());
    }

    public function fetchSingleObject($host, $service = null)
    {
        $db = $this->getIdo()->getDb();
        $helper = new QueryHelper($db);

        return $this->addVarsToQuery(
            $helper,
            $helper->filterObject(
                $helper->selectBmcObjects(),
                $host,
                $service
            )
        );
    }

    /**
     * @return \stdClass[]
     */
    public function fetchEvents()
    {
        return $this->getIdo()->getDb()->fetchAll($this->selectEvents());
    }

    protected function addVarsToQuery(QueryHelper $helper, DbSelect $query)
    {
        foreach ($this->getRequiredVarNames() as $varName) {
            $helper->requireCustomVar($query, $varName);
        }
        foreach ($this->getRequiredVarNames() as $varName) {
            $helper->requireCustomVar($query, $varName);
        }

        return $query;
    }

    /**
     * @return Event[]
     */
    public function fetchOverdueEvents()
    {
        return [];
    }

    public function getObjectUrl($host, $service = null)
    {
        if ($service === null) {
            return $this->getHostUrl($host);
        } else {
            return $this->getServiceUrl($host, $service);
        }
    }

    public function getHostUrl($host)
    {
        return $this->getIcingaWebUrl('monitoring/show/host', ['host' => $host]);
    }

    public function getServiceUrl($host, $service)
    {
        return $this->getIcingaWebUrl('monitoring/show/host', [
            'host'    => $host,
            'service' => $service,
        ]);
    }

    public function getIcingaWebUrl($path = null, $params = [])
    {
        $url = rtrim(
            $this->config->get(
                'icingaweb',
                'url',
                'https://mon.example.com/icingaweb2/'
            ),
            '/'
        );

        if ($path !== null) {
            $url .= '/' . ltrim($path, '/');
        }

        if (empty($params)) {
            return $url;
        } else {
            return $this->appendParams($url, $params);
        }
    }

    protected function appendParams($url, $params)
    {
        $query = [];
        foreach ($params as $k => $v) {
            $query[rawurldecode($k)] = rawurlencode($v);
        }

        return $url . '?' . implode('&', $query);
    }

    /**
     * @return ImpactPoster
     */
    public function msend()
    {
        if ($this->msend === null) {
            $this->msend = new ImpactPoster(
                $this->config->get('bem', 'cell'),
                $this->config->get('bem', 'object_class', 'ICINGA'),
                $this->config->get('bem', 'prefix_dir', '/usr/local/msend')
            );
        }

        return $this->msend;
    }

    public function getVarNames()
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
}
