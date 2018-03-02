<?php

namespace Icinga\Module\Bem\Web\Table;

use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;
use Icinga\Date\DateFormatter;
use Icinga\Module\Bem\Config\CellConfig;
use Icinga\Module\Bem\Util;

class BemIssueTable extends ZfQueryBasedTable
{
    private $lastHost;

    /** @var CellConfig */
    private $cell;

    protected $defaultAttributes = [
        'class' => ['common-table', 'state-table', 'table-row-selectable'],
        'data-base-target' => '_next'
    ];

    protected $searchColumns = [
        'severity',
        'host_name',
        'object_name',
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

    public function getColumnsToBeRendered()
    {
        return ['Host/Service', 'Severity', 'Cell', 'Schedule'];
    }

    protected function hostHasChanged($row)
    {
        if ($row->host_name === $this->lastHost) {
            return false;
        } else {
            $this->lastHost = $row->host_name;
            return true;
        }
    }

    protected function renderObjectName($host, $object = null)
    {
        if ($object === null) {
            return Link::create($host, 'bem/issue', [
                'host' => $host,
                'cell' => $this->cell->getName()
            ]);
        } else {
            return Link::create("$host: $object", 'bem/object', [
                'host'   => $host,
                'object' => $object,
                'cell'   => $this->cell->getName()
            ]);
        }
    }

    public function renderRow($row)
    {
        return $this::row([
            $this->renderObjectName($row->host_name, $row->object_name),
            $row->severity,
            $row->cell_name,
            DateFormatter::timeUntil($row->ts_next_notification / 1000, true)
        ])->setAttributes([
            'class' => Util::cssClassForSeverity($row->severity)
        ]);
    }

    protected function prepareQuery()
    {
        return $this->cell->db()->select()->from('bem_issue', [
            'cell_name',
            'host_name',
            'object_name',
            'severity',
            'ts_next_notification'
        ])->order('host_name')->order('object_name');
    }
}
