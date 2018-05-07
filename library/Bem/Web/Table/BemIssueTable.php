<?php

namespace Icinga\Module\Bem\Web\Table;

use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;
use Icinga\Module\Bem\Config\CellConfig;
use Icinga\Module\Bem\Util;
use Icinga\Module\Bem\Web\Widget\NextNotificationRenderer;

class BemIssueTable extends ZfQueryBasedTable
{
    private $lastHost;

    /** @var CellConfig */
    private $cell;

    protected $defaultAttributes = [
        'class' => ['common-table', 'table-row-selectable'],
        'data-base-target' => '_next'
    ];

    protected $searchColumns = [
        'severity',
        'ci_name',
    ];

    /**
     * @param CellConfig $cell
     * @return static
     * @throws \Icinga\Exception\ProgrammingError
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
        return ['Host/Service', 'Severity', 'Cell', 'Scheduled'];
    }

    protected function renderObjectLink($ci)
    {
        if (strpos($ci, '!') === false) {
            return Link::create($ci, 'bem/issue', [
                'host' => $ci,
                'cell' => $this->cell->getName()
            ]);
        } else {
            list($host, $service) = preg_split('/!/', $ci, 2);

            return Link::create("$host: $service", 'bem/issue', [
                'host'    => $host,
                'service' => $service,
                'cell'    => $this->cell->getName()
            ]);
        }
    }

    public function renderRow($row)
    {
        return $this::row([
            $this->renderObjectLink($row->ci_name),
            $row->severity,
            $row->cell_name,
            new NextNotificationRenderer($row->ts_next_notification)
        ])->setAttributes([
            'class' => Util::cssClassForSeverity($row->severity)
        ]);
    }

    protected function prepareQuery()
    {
        return $this->cell->db()->select()->from('bem_issue', [
            'cell_name',
            'ci_name',
            'severity',
            'ts_next_notification'
        ])->order('host_name')->order('ci_name');
    }
}
