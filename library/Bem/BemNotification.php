<?php

namespace Icinga\Module\Bem;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Bem\Config\CellConfig;
use Icinga\Module\Bem\Object\PropertyContainer;

class BemNotification
{
    use PropertyContainer;

    protected $defaultProperties = [
        'bem_event_id'     => null,
        'ci_name_checksum' => null,
        'ci_name'          => null,
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

    private $id;

    protected function __construct(CellConfig $cell)
    {
        $this->cell = $cell;
    }

    /**
     * @param CellConfig $cell
     * @param $id
     * @return mixed
     * @throws NotFoundError
     */
    public static function loadFromLog(CellConfig $cell, $id)
    {
        $object = new static($cell);
        $db = $cell->db();
        $result = $db->fetchRow(
            $db->select()
                ->from('bem_notification_log')
                ->where('id = ?', $id)
        );

        if (! $result) {
            throw new NotFoundError(
                'Notification log entry %s not found',
                $id
            );
        }
        $object->id = $id;
        unset($result->id);

        $object->fillWithDefaultProperties();

        return $object->setProperties($result);
    }

    /**
     * @param BemIssue $issue
     * @return static
     */
    public static function forIssue(BemIssue $issue)
    {
        $object = new static($issue->getCell());
        $object->fillWithDefaultProperties();
        $object->setBemIssueProperties($issue);

        return $object;
    }

    /**
     * @param BemIssue $issue
     * @return $this
     * @throws \Icinga\Exception\IcingaException
     */
    public function setBemIssueProperties(BemIssue $issue)
    {
        $properties = [
            'ci_name_checksum',
            'ci_name',
            'severity',
            'slot_set_values',
        ];

        foreach ($properties as $property) {
            $this->set($property, $issue->get($property));
        }

        return $this;
    }

    /**
     * @return array
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

        return (array) $this->slotSetValues;
    }

    /**
     * @param $key
     * @param null $default
     * @return mixed|null
     * @throws \Icinga\Exception\IcingaException
     */
    public function getSlotSetValue($key, $default = null)
    {
        $values = $this->getSlotSetValues();
        if (array_key_exists($key, $values)) {
            return $values[$key];
        } else {
            return $default;
        }
    }

    /**
     * Calculates timestamp (in ms) for the next notification
     *
     * In case we didn't get a valid event ID,
     *
     * @return int
     * @throws \Icinga\Exception\IcingaException
     */
    public function calculateNextNotification()
    {
        if ($this->get('bem_event_id') === null) {
            // We haven't been able to get an EventId, retry in 15 seconds
            return Util::timestampWithMilliseconds() + 15 * 1000;
        }

        $mcTimeout = $this->getSlotSetValue('mc_timeout');
        if ($mcTimeout === null) {
            // Default re-notification interval: 15min
            return Util::timestampWithMilliseconds() + 900 * 1000;
        }

        // mc_timeout is in seconds, so it should multiply with 1000.
        // We want the re-notification to occur after 50% of that time
        return Util::timestampWithMilliseconds() + $mcTimeout * 500;
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     * @throws \Zend_Db_Adapter_Exception
     */
    public function storeToLog()
    {
        $this->cell->db()->insert('bem_notification_log', $this->getProperties());
    }
}
