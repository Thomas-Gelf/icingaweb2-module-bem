<?php

namespace Icinga\Module\Bem;

use Icinga\Module\Bem\Config\CellConfig;
use Icinga\Module\Bem\Object\PropertyContainer;

class BemIssue
{
    use PropertyContainer;

    protected $defaultProperties = [
        'ci_name_checksum'      => null,
        'cell_name'             => null,
        'host_name'             => null,
        'object_name'           => null,
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

    /** @var CellConfig */
    private $cell;

    protected function __construct(CellConfig $cell)
    {
        $this->cell = $cell;
    }

    public static function forIcingaObject($icingaObject, CellConfig $cell)
    {
        $db = $cell->db();

        $object = new static($cell);
        $object->fillWithDefaultProperties();
        $object->setIcingaObject($icingaObject);

        $result = $db->fetchRow($object->prepareSelectQuery());
        if ($result) {
            $newProperties = $object->getPropertiesForDb();
            $object->setProperties($result);
            $object->setUnmodified();
            $object->setProperties($newProperties);
            $object->hasBeenStored = true;
        }

        return $object;
    }

    public function isNew()
    {
        return ! $this->hasBeenStored;
    }

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

    protected function checkWorstSeverity()
    {
        // TODO: correct implementation, even if currently unused
        $worst = $this->get('worst_severity');
        if ($worst === null) {
            $this->set('worst_severity', $this->get('severity'));
        }
    }

    protected function insert()
    {
        $this->cell->db()->insert(
            'bem_issue',
            $this->getPropertiesForDb()
        );
    }

    protected function update()
    {
        $db = $this->cell->db();
        $db->update(
            'bem_issue',
            $this->getModifiedProperties(),
            $db->quoteInto('ci_name_checksum = ?', $this->get('ci_name_checksum'))
        );
    }

    protected function prepareSelectQuery()
    {
        return $this->cell->db()->select()
            ->from('bem_issue')
            ->where('ci_name_checksum = ?', $this->get('ci_name_checksum'));
    }

    protected static function calculateCiChecksum(CellConfig $config, $host, $service = null)
    {
        $parts = [
            $config->getName(),
            $host
        ];

        if ($service !== null) {
            $parts[] = $service;
        }

        return sha1(implode('!', $parts), true);
    }

    protected function recalculateCiCheckSum()
    {
        $this->set('ci_name_checksum', sha1(implode('!', [
            $this->cell->getName(),
            $this->get('host_name'),
            $this->get('object_name')
        ]), true));
    }

    public function setIcingaObject($object)
    {
        $this->set('cell_name', $this->cell->getName());
        $this->set('host_name', $object->host_name);
        $this->set('severity', 'CRITICAL');
        $params = $this->cell->fillParams($object);
        $this->set('slot_set_values', json_encode($params));

        // TODO: define whether mc_host and mc_object should be required
        $this->set('host_name', $params['mc_host']);
        $this->set('object_name', $params['mc_object']);
        $this->recalculateCiCheckSum();
        $this->checkWorstSeverity();

        return $this;
    }

    public function scheduleNextNotification($timestampMs = null)
    {
        if ($timestampMs === null) {
            $timestampMs = Util::timestampWithMilliseconds();
        }

        $this->set('ts_next_notification', $timestampMs);
        if ($this->get('cnt_notifications') === null) {
            $this->set('cnt_notifications', 0);
        }

        return $this;
    }

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
