<?php

namespace Icinga\Module\Bem\Web\Table;

use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;
use Icinga\Date\DateFormatter;
use Icinga\Module\Bem\BemIssue;
use Icinga\Module\Bem\Config\CellConfig;

class NotificationLogTable extends ZfQueryBasedTable
{
    /** @var CellConfig */
    private $cell;

    protected $defaultAttributes = [
        'class' => ['common-table', 'table-row-selectable'],
        'data-base-target' => '_next'
    ];

    protected $searchColumns = [
        'host_name',
        'service_name',
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

    /**
     * Well... it's the CI, not the issue
     *
     * @param BemIssue $issue
     * @return $this;
     */
    public function filterIssue(BemIssue $issue)
    {
        $this->getQuery()->where('ci_name_checksum = ?', $issue->getKey());

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
            DateFormatter::formatTime($ts),
            Link::create(
                $this->renderObjectName($row->host_name, $row->object_name),
                'bem/notification',
                ['id' => $row->id, 'cell' => $this->cell->getName()]
            )
        ]);
    }

    protected function prepareQuery()
    {
        return $this->cell->db()->select()->from('bem_notification_log', [
            'id',
            'ts_notification',
            'host_name',
            'object_name',
            'severity',
        ])->order('ts_notification DESC');
    }
}
