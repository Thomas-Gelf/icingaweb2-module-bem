<?php

namespace Icinga\Module\Bem;

use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;
use Icinga\Module\Bem\Config\CellConfig;

class BemIssueTable extends ZfQueryBasedTable
{
    private $lastHost;

    private $showOnlyProblems = true;

    /** @var CellConfig */
    private $cell;

    private $checksums = [];

    private $issueDetails;

    protected $defaultAttributes = [
        'class' => ['common-table', 'state-table', 'table-row-selectable'],
        'data-base-target' => '_next'
    ];

    protected $searchColumns = [
        'host_name',
        'service_name',
        'host.vars.bmc_object_class',
        'host.vars.contact_team',
        'output'
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

    public function getColumnsToBeRendered()
    {
        return ['Host/Service', 'Output', 'Contact', 'BMC'];
    }

    public function showOnlyProblems($onlyProblems = true)
    {
        $this->showOnlyProblems = (bool) $onlyProblems;
        return $this;
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

    protected function renderObjectName($host, $service = null)
    {
        if ($service === null) {
            return Link::create($host, 'bem/notification', [
                'host' => $host
            ]);
        } else {
            return Link::create("$host: $service", 'bem/notification', [
                'host'    => $host,
                'service' => $service
            ]);
        }
    }

    public function renderRow($row)
    {
        return $this::row([
            $this->renderObjectName($row->host, $row->service),
            $row->last_output,
            $row->last_exit_code,
        ]);
    }

    protected function prepareQuery()
    {
        $issues = new BemIssues($this->cell->db());
        return $issues->selectIssues();
    }
}
