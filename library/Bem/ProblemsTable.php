<?php

namespace Icinga\Module\Bem;

use ipl\Html\Element;
use ipl\Html\Link;
use ipl\Web\Table\ZfQueryBasedTable;

class ProblemsTable extends ZfQueryBasedTable
{
    private $lastHost;

    private $showOnlyProblems = true;

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

    public function getColumnsToBeRendered()
    {
        return ['Host', 'Service', 'Output', 'Contact', 'BMC'];
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

    protected function addHeaderColumnsTo(Element $parent)
    {
        foreach (['Host', 'Service', 'Output'] as $column) {
            $parent->add($this::th($column));
        }
        foreach (['Contact', 'BMC'] as $column) {
            $parent->add($this::th($column, ['class' => 'hide-when-compact']));
        }

        return $parent;
    }

    public function renderRow($row)
    {
        $newHost = $this->hostHasChanged($row);
        
        $tr = $this::tr();

        if ($row->service_name === null) {
            $tr->attributes()->add('class', 'host');
            $tr->add($this->tdHost($row)->addAttributes(['colspan' => 2]));
        } else {
            $tr->attributes()->add('class', 'service');
            if ($newHost) {
                $tr->add([$this->tdHost($row), $this->tdService($row)]);
            } else {
                $tr->add([$this::td(''), $this->tdService($row)]);
            }
        }

        $tr->add([
            $this::td($this->fixOutput($row->output)),
            $this::td($row->{'host.vars.contact_team'},['class' => 'hide-when-compact']),
            $this::td($row->{'host.vars.bmc_object_class'}, ['class' => 'hide-when-compact']),
        ]);

        return $tr;
    }

    protected function fixOutput($output)
    {
        return preg_replace('/@{5,}/', '@@@@@', $output);
    }

    protected function tdHost($row)
    {
        $classes = [self::$hostStateClass[$row->host_hard_state]];
        if ($row->host_in_downtime === 'y' || $row->host_acknowledged === 'y') {
            $classes[] = 'handled';
        }
        return $this::td(Link::create($row->host_name, 'monitoring/host/show', [
            'host' => $row->host_name
        ]))->addAttributes([
            'class' => $classes
        ]);
    }

    protected function tdService($row)
    {
        $classes = [self::$serviceStateClass[$row->service_hard_state]];
        if ($row->service_in_downtime === 'y' || $row->service_acknowledged === 'y') {
            $classes[] = 'handled';
        }
        return $this::td(Link::create($row->service_name, 'monitoring/service/show', [
            'host'    => $row->host_name,
            'service' => $row->service_name
        ], ['class' => 'rowaction']))->addAttributes([
            'class' => $classes
        ]);
    }

    protected function prepareQuery()
    {
        $helper = new QueryHelper($this->db());
        if ($this->showOnlyProblems) {
            return $helper->selectProblemsForBmc();
        } else {
            return $helper->selectBmcObjects();
        }
    }
}
