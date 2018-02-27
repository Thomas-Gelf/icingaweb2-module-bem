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
}
