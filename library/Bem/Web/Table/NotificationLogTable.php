<?php

namespace Icinga\Module\Bem\Web\Table;

use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;
use Icinga\Date\DateFormatter;
use Icinga\Module\Bem\Config\CellConfig;

class NotificationLogTable extends ZfQueryBasedTable
{
    /** @var CellConfig */
    private $cell;

    protected $defaultAttributes = [
        'class' => ['common-table', 'state-table', 'table-row-selectable'],
        'data-base-target' => '_next'
    ];

    protected $searchColumns = [
        'host_name',
        'service_name',
    ];

    private static $hostStateClass = [
        0 => 'state-up',
        1 => 'state-down',
    ];

    private static $serviceStateClass = [
        0 => 'state-ok',
        1 => 'state-warning',
        2 => 'state-critical',
        3 => 'state-unknown',
    ];

    /**
     * @param CellConfig $cell
     * @return static
     */
    public static function forCell(CellConfig $cell)
    {
        $self = new static($cell->db());

        return $self->setCell($cell);
    }

    public function setCell(CellConfig $cell)
    {
        $this->cell = $cell;

        return $this;
    }

    protected function renderObjectName($host, $service = null)
    {
        if ($service === null) {
            return $host;
        } else {
            return "$host: $service";
        }
    }

    public function renderRow($row)
    {
        $ts = $row->ts_notification / 1000;
        $this->splitByDay($ts);

        return $this::row([
            DateFormatter::formatDate($ts),
            Link::create(
                $this->renderObjectName($row->host_name, $row->object_name),
                'bem/notification',
                ['id' => $row->id, 'cell' => $this->cell->getName()]
            )
        ]);
    }

    protected function prepareQuery()
    {
        return $this->cell->db()
            ->select()
            ->from('bem_notification_log', [
                'id',
                'ts_notification',
                'host_name',
                'object_name',
            ])->order('ts_notification DESC');
    }
}
