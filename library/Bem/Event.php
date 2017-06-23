<?php

namespace Icinga\Module\Bem;

class Event
{
    protected $object;

    /** @var int */
    private $id;

    protected function __construct($props)
    {
        $this->props = $props;
    }

    /**
     * @param IdoDb $ido
     * @param string $host
     * @param string|null $service
     * @return static
     */
    public static function fromIdo(IdoDb $ido, $host, $service = null)
    {
        $props = $ido->getStateRowFor($host, $service);
        return new static($props);
    }

    public static function fromProblemQueryRow($row)
    {
        return new static($row);
    }

    public function getHostName()
    {
        return $this->props->host_name;
    }

    public function getServiceName()
    {
        return $this->props->service_name;
    }

    public function getUniqueObjectName()
    {
        if ($this->isService()) {
            return $this->getHostName() . '!' . $this->getServiceName();
        } else {
            return $this->getHostName();
        }
    }

    public function getObjectChecksum()
    {
        return sha1($this->getUniqueObjectName(), true);
    }

    /**
     * Event ID as assigned by BEM.
     *
     * This will usually be set by ImactPoster after sending the event
     *
     * @param $id
     * @return int
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this->id;
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function isIssue()
    {
        return ! in_array($this->getHardStateName(), ['OK', 'UP']);
    }

    public function getPriority()
    {
        return 'PRIORITY_4';
        // From config?
        return $this->getHostVar('priority');
    }

    public function getSeverity()
    {
        return $this->getHardStateName();
    }

    public function getHardStateName()
    {
        if ($this->isService()) {
            return StateMapper::getIcingaServiceState($this->props->service_hard_state);
        } else {
            return StateMapper::getIcingaHostState($this->props->host_hard_state);
        }

        // Former variant, currently it's not correct:
        return StateMapper::getFinalSeverityForObject($this->object);
    }

    public function getEscapedParameters()
    {
        $params = array();
        foreach ($this->getMcParameters() as $key => $v) {
//             if (preg_match('/^[a-z]+[a-z0-9_-]*[a-z0-9]+$/i', $v)) {
            if (preg_match('/^[a-z0-9_-]+$/i', $v)) {
                $value = $v;
            } else {
//                $value = $v;
                $value = escapeshellarg($v);
            }
            $params[] = "$key=" . $value;
        }

        return implode(';', $params);
    }

    public function getMcParameters()
    {
        $parameters = array(
            'msg'             => $this->getMessage(),
            'mc_host'         => $this->stripDomain($this->getHostName()),
            'mc_object'       => $this->getMcObject(),
            'mc_object_class' => $this->getMcObjectClass(),
            'mc_object_uri'   => $this->getMcObjectUrl(),
            'mc_priority'     => $this->getPriority(),
            'mc_parameter'    => 'status', // needless? Delegate to config.
            'mc_timeout'      => $this->getMcTimeout(),
            'my_os'          => $this->getMyOs(),
        );

        return array_merge(
            $parameters,
            $this->getOptionalParameters()
        );
    }

    public function getOptionalParameters()
    {
        $map = array(
            'my_contact'        => 'getMyContact',
            // 'my_knowledge_base' => 'getMyKnowledgeBase',
        );

        $parameters = array();

        foreach ($map as $key => $func) {
            if (null !== ($value = $this->$func())) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    public function getMessage()
    {
        // <a href="https://monitoring/...">Service Name</a> Check output [..]
        return sprintf(
            '%s: %s',
            $this->getMcObjectUrl(),
            $this->getStatusLine()
        );
    }

    public function getMcObject()
    {
        if ($this->isService()) {
            return $this->getServiceName();
        } else {
            return 'hoststatus';
        }
    }

    /**
     * BMC will drop the event after this many seconds
     *
     * @return int
     */
    public function getMcTimeout()
    {
        // TODO: get from Cell config
        return 7200;
    }

    public function getStatusLine()
    {
        return $this->props->output;
    }

    protected function stripDomain($fqdn)
    {
        return substr($fqdn, 0, strpos($fqdn, '.'));
    }

    public function getMcObjectClass()
    {
        return $this->getHostVar('bmc_object_class');
    }

    public function isService()
    {
        return $this->props->service_name !== null;
    }

    public function getMyOs()
    {
        return $this->getHostVar('contact_team');
    }

    protected function getHostVar($name, $default = null)
    {
        $key = "host.vars.$name";
        $props = $this->props;
        if (property_exists($this->props, $key)) {
            return $this->props->$key;
        } else {
            return $default;
        }
    }

    public function getMyContact()
    {
        return $this->getHostVar('contact_team');
    }

    public function getMcObjectUrl()
    {
        return sprintf(
            '<a href="%s">%s</a>',
            $this->getObjectUrl(),
            htmlspecialchars($this->getShortObjectName())
        );
    }

    protected function getShortObjectName()
    {
        if ($this->isService()) {
            return $this->props->service_name;
        } else {
            return $this->props->host_name;
        }
    }

    protected function getObjectUrl()
    {
        if ($this->isService()) {
            return $this->getServiceUrl();
        } else {
            return $this->getHostUrl();
        }
    }

    protected function getHostUrl()
    {
        return $this->getUrl(sprintf(
            'monitoring/host/show?host=%s',
            rawurlencode($this->props->host_name)
        ));
    }

    protected function getServiceUrl()
    {
        return $this->getUrl(sprintf(
            'monitoring/service/show?host=%s&service=%s',
            rawurlencode($this->props->host_name),
            rawurlencode($this->props->service_name)
        ));
    }

    protected function getUrl($sub)
    {
        return $this->getBaseUrl() . $sub;
    }

    protected function getBaseUrl()
    {
        return 'https://monitoring/icingaweb2/';
    }
}
