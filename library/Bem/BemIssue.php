<?php

namespace Icinga\Module\Bem;

use Icinga\Application\Logger;
use Icinga\Date\DateFormatter;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Bem\Config\CellConfig;
use Icinga\Module\Bem\Object\PropertyContainer;

class BemIssue
{
    use PropertyContainer;

    protected $defaultProperties = [
        'ci_name_checksum'      => null,
        'cell_name'             => null,
        'ci_name'               => null,
        'host_name'             => null,
        'object_name'           => null,
        'is_relevant'           => null,
        'severity'              => null,
        'worst_severity'        => null,
        'slot_set_values'       => null,
        'ts_first_notification' => null,
        'ts_last_notification'  => null,
        'ts_next_notification'  => null,
        'cnt_notifications'     => null,
    ];

    protected $slotSetValues;

    protected $hasBeenStored = false;

    protected $tableName = 'bem_issue';

    protected $severityComparisonMap = [
        'OK'       => 0,
        'INFO'     => 1,
        'WARNING'  => 2,
        'MINOR'    => 3,
        'UNKNOWN'  => 4,
        'MAJOR'    => 5,
        'CRITICAL' => 6,
        'DOWN'     => 7,
    ];

    /** @var CellConfig */
    private $cell;

    protected function __construct(CellConfig $cell)
    {
        $this->cell = $cell;
    }

    /**
     * @return mixed
     * @throws \Icinga\Exception\IcingaException
     */
    public function getKey()
    {
        return $this->get('ci_name_checksum');
    }

    /**
     * @param $icingaObject
     * @param CellConfig $cell
     * @return BemIssue|static
     * @throws \Icinga\Exception\IcingaException
     */
    public static function forIcingaObject($icingaObject, CellConfig $cell)
    {
        $db = $cell->db();

        $object = new static($cell);
        $object->fillWithDefaultProperties();
        $object->setIcingaObject($icingaObject);

        $result = $db->fetchRow($object->prepareSelectQuery());
        if ($result) {
            $newProperties = $object->getPropertiesForDb();
            $object = static::forDbRow($result, $cell);
            $object->setProperties($newProperties);
        }

        return $object;
    }

    public static function load(CellConfig $cell, $host, $service = null)
    {
        return static::forDbRow(
            $cell->db()->fetchRow(static::prepareSelectQueryFor($cell, $host, $service)),
            $cell
        );
    }

    public static function forDbRow($row, CellConfig $cell)
    {
        $object = new static($cell);
        $object->fillWithDefaultProperties();
        if ($row) {
            $object->setProperties($row);
            $object->setUnmodified();
            $object->hasBeenStored = true;
        }

        return $object;
    }

    /**
     * @return bool
     * @throws \Icinga\Exception\IcingaException
     */
    public function isRelevant()
    {
        return $this->get('is_relevant') === 'y';
    }

    /**
     * @return CellConfig
     */
    public function getCell()
    {
        return $this->cell;
    }

    public function isNew()
    {
        return ! $this->hasBeenStored;
    }

    /**
     * @param $dueTime
     * @return bool
     * @throws \Icinga\Exception\IcingaException
     */
    public function isDueIn($dueTime)
    {
        return $this->get('ts_next_notification') <= $dueTime;
    }

    /**
     * @return mixed|string
     * @throws \Icinga\Exception\IcingaException
     */
    public function getNiceName()
    {
        return static::makeNiceCiName($this->get('ci_name'));
    }

    public static function makeNiceCiName($ciName)
    {
        if (strpos($ciName, '!') === false) {
            return $ciName;
        } else {
            return implode(': ', preg_split('/!/', $ciName, 2));
        }
    }

    /**
     * @param $ciName
     * @return array[]  Array of type [host, service] - service can be null
     */
    public static function splitCiName($ciName)
    {
        if (strpos($ciName, '!') === false) {
            return [$ciName, null];
        } else {
            return preg_split('/!/', $ciName, 2);
        }
    }

    /**
     * @return array
     * @throws \Icinga\Exception\IcingaException
     */
    public function getUrlParams()
    {
        $ciName = $this->get('ci_name');
        if (strpos($ciName, '!') === false) {
            return [
                'host'   => $ciName,
                'cell'   => $this->cell->getName()
            ];
        } else {
            list($host, $service) = preg_split('/!/', $ciName, 2);

            return [
                'host'   => $host,
                'object' => $service,
                'cell'   => $this->cell->getName()
            ];
        }
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     * @throws \Zend_Db_Adapter_Exception
     */
    public function store()
    {
        if ($this->hasBeenModified()) {
            if ($this->isNew()) {
                $this->insert();
            } else {
                $this->update();
            }
            $this->hasBeenStored = true;
        }
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     */
    public function refreshWorstSeverity()
    {
        $this->set('worst_severity', $this->getWorseSeverity(
            $this->get('severity'),
            $this->get('worst_severity')
        ));
    }

    /**
     * @param $severity
     * @return mixed
     * @throws InvalidPropertyException
     */
    protected function getSeverityComparisonValue($severity)
    {
        if (array_key_exists($severity, $this->severityComparisonMap)) {
            return $this->severityComparisonMap[$severity];
        } else {
            throw new InvalidPropertyException('Valid severity expected, got %s', $severity);
        }
    }

    /**
     * @param $a
     * @param $b
     * @return mixed
     * @throws InvalidPropertyException
     */
    protected function getWorseSeverity($a, $b)
    {
        if ($a === null) {
            return $b;
        } elseif ($b === null) {
            return $a;
        }

        if ($this->getSeverityComparisonValue($a) > $this->getSeverityComparisonValue($b)) {
            return $a;
        } else {
            return $b;
        }
    }

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function insert()
    {
        if ($this->cell->db()->insert(
            $this->tableName,
            $this->getPropertiesForDb()
        )) {
            $this->setUnmodified();
        }
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function update()
    {
        $db = $this->cell->db();
        if ($db->update(
            $this->tableName,
            $this->getModifiedProperties(),
            $this->createWhere()
        )) {
            $this->setUnmodified();
        }
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     */
    public function delete()
    {
        if ($this->cell->db()->delete(
            $this->tableName,
            $this->createWhere()
        )) {
            $this->hasBeenStored = false;
            foreach ($this->listProperties() as $key) {
                if ($this->get($key) !== $this->defaultProperties[$key]) {
                    $this->modifiedProperties[$key] = true;
                }
            }
        }
    }

    /**
     * @return string
     * @throws \Icinga\Exception\IcingaException
     */
    public function createWhere()
    {
        return $this->cell->db()->quoteInto('ci_name_checksum = ?', $this->getKey());
    }

    /**
     * @return \Zend_Db_Select
     * @throws \Icinga\Exception\IcingaException
     */
    protected function prepareSelectQuery()
    {
        return $this->cell->db()->select()
            ->from('bem_issue')
            ->where('ci_name_checksum = ?', $this->getKey());
    }

    /**
     * @param CellConfig $cell
     * @param $host
     * @param $service
     * @return \Zend_Db_Select
     */
    protected static function prepareSelectQueryFor(CellConfig $cell, $host, $service)
    {
        return $cell->db()->select()
            ->from('bem_issue')
            ->where('ci_name_checksum = ?', static::calculateChecksum(
                $cell,
                static::makeCiName($host, $service)
            ));
    }

    /**
     * @param CellConfig $cell
     * @param $host
     * @param $object
     * @return string
     */
    protected static function calculateChecksum(CellConfig $cell, $ciName)
    {
        return sha1($cell->getName() . '!' . $ciName, true);
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     */
    protected function recalculateCiCheckSum()
    {
        $this->set(
            'ci_name_checksum',
            static::calculateChecksum($this->cell, $this->get('ci_name'))
        );
    }

    /**
     * @param $object
     * @return $this
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\IcingaException
     */
    public function setIcingaObject($object)
    {
        $this->set('cell_name', $this->cell->getName());
        $this->set('ci_name', $this->makeCiName($object->host_name, $object->service_name));
        $this->recalculateCiCheckSum();
        $this->set('severity', $this->cell->calculateSeverityForIcingaObject($object));
        $this->refreshWorstSeverity();

        // Set severity properties for fillParams
        $object->severity = $this->get('severity');
        $object->worst_severity = $this->get('worst_severity');
        $params = $this->cell->fillParams($object);
        $this->set('slot_set_values', json_encode($params));

        // TODO: define whether mc_host and mc_object should be required
        $this->set('host_name', $params['mc_host']);
        $this->set('object_name', $params['mc_object']);
        $this->set('is_relevant', $this->cell->wantsIcingaObject(
            $object
        ) ? 'y' : 'n');

        return $this;
    }

    public function isProblem()
    {
        return $this->severity !== 'OK';
    }

    /**
     * @param $host
     * @param null $service
     * @return string
     */
    public static function makeCiName($host, $service = null)
    {
        if ($service === null) {
            return $host;
        } else {
            return "$host!$service";
        }
    }

    /**
     * @param null $timestampMs
     * @return $this
     * @throws \Icinga\Exception\IcingaException
     */
    public function scheduleNextNotification($timestampMs = null)
    {
        if ($timestampMs === null) {
            $timestampMs = Util::timestampWithMilliseconds();
            Logger::debug(
                'Scheduling next notification for %s (NOW!)',
                DateFormatter::formatDateTime($timestampMs / 1000)
            );
        } else {
            Logger::debug(
                'Scheduling next notification for %s',
                DateFormatter::formatDateTime($timestampMs / 1000)
            );
        }

        $this->set('ts_next_notification', $timestampMs);
        if ($this->get('cnt_notifications') === null) {
            $this->set('cnt_notifications', 0);
        }

        return $this;
    }

    /**
     * @return array|mixed
     * @throws \Icinga\Exception\IcingaException
     */
    public function getSlotSetValues()
    {
        if ($this->slotSetValues === null) {
            $value = $this->get('slot_set_values');
            if ($value === null) {
                return [];
            } else {
                $this->slotSetValues = json_decode($value);
            }
        }

        return $this->slotSetValues;
    }
}
