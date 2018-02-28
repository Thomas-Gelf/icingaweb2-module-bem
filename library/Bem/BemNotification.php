<?php

namespace Icinga\Module\Bem;

use Icinga\Module\Bem\Config\CellConfig;
use Icinga\Module\Bem\Object\PropertyContainer;

class BemNotification
{
    use PropertyContainer;

    protected $defaultProperties = [
        'bem_event_id'     => null,
        'ci_name_checksum' => null,
        'host_name'        => null,
        'object_name'      => null,
        'severity'         => null,
        'slot_set_values'  => null,
        'ts_notification'  => null,
        'duration_ms'      => null,
        'pid'              => null,
        'system_user'      => null,
        'system_host_name' => null,
        'exit_code'        => null,
        'command_line'     => null,
        'output'           => null,
    ];

    protected $slotSetValues;

    /** @var CellConfig */
    private $cell;

    protected function __construct(CellConfig $cell)
    {
        $this->cell = $cell;
    }

    public static function forIcingaObject($icingaObject, CellConfig $cell)
    {
        $object = new static($cell);
        $object->fillWithDefaultProperties();
        $object->setIcingaObject($icingaObject);

        return $object;
    }

    public function setIcingaObject($object)
    {
        $this->set('host_name', $object->host_name);
        $this->set('severity', 'CRITICAL');
        $params = $this->cell->fillParams($object);
        $this->set('slot_set_values', json_encode($params));

        // TODO: define whether mc_host and mc_object should be required
        $this->set('host_name', $params['mc_host']);
        $this->set('object_name', $params['mc_object']);
        $this->set('ci_name_checksum', sha1(
            $this->get('host_name') . '!' . $this->get('object_name')
        ));

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

    public function storeToLog()
    {
        $this->cell->db()->insert('bem_notification_log', $this->getProperties());
    }
}
