<?php

namespace Icinga\Module\Bem\Config;

class IcingaWebUrlHelper
{
    protected $baseUrl;

    public function __construct(CellConfig $cellConfig)
    {
        $this->baseUrl = rtrim($cellConfig->get(
            'icingaweb',
            'url',
            'https://mon.example.com/icingaweb2/'
        ), '/');
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

    protected function getIcingaWebUrl($path = null, $params = [])
    {
        $url = $this->baseUrl;

        if ($path !== null) {
            $url .= '/' . ltrim($path, '/');
        }

        return $this->appendParams($url, $params);
    }

    protected function appendParams($url, $params)
    {
        if (empty($params)) {
            return $url;
        }

        $query = [];
        foreach ($params as $k => $v) {
            $query[] = rawurlencode($k) . '=' . rawurlencode($v);
        }

        return $url . '?' . implode('&', $query);
    }
}
