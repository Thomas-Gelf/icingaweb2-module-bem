<?php

namespace Icinga\Module\Bem;

use ipl\Html\DeferredText;
use ipl\Html\Element;
use ipl\Html\Link;
use ipl\Web\Table\ZfQueryBasedTable;

class ProblemsTable extends ZfQueryBasedTable
{
    private $lastHost;

    private $showOnlyProblems = true;

    /** @var Cell */
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

    public function setCell(Cell $cell)
    {
        $this->cell = $cell;
        return $this;
    }

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
            // $parent->add($this::th($column, ['class' => 'hide-when-compact']));
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
            $checksum = sha1($row->host_name, true);
        } else {
            $tr->attributes()->add('class', 'service');
            if ($newHost) {
                $tr->add([$this->tdHost($row), $this->tdService($row)]);
            } else {
                $tr->add([$this::td(''), $this->tdService($row)]);
            }
            $checksum = sha1($row->host_name . '!' . $row->service_name, true);
        }

        $self = $this;
        $this->checksums[$checksum] = $checksum;
        $tr->add([
            $this::td($this->fixOutput($row->output)),
            $this::td(DeferredText::create(function () use ($self, $checksum) {
                $details = $this->getDetailsForChecksum($checksum);
                if ($details === null) {
                    return 0;
                } else {
                    return $details->cnt_notifications;
                }
            })),
//            $this::td($row->{'host.vars.contact_team'}, ['class' => 'hide-when-compact']),
//            $this::td($row->{'host.vars.bmc_object_class'}, ['class' => 'hide-when-compact']),
        ]);

        return $tr;
    }

    public function getDetailsForChecksum($checksum)
    {
        if ($this->issueDetails === null) {
            $this->issueDetails = [];
            foreach ($this->fetchIssueDetails() as $row) {
                $this->issueDetails[$row->checksum] = $row;
            }
        }

        if (array_key_exists($checksum, $this->issueDetails)) {
            return $this->issueDetails[$checksum];
        } else {
            return null;
        }
    }

    protected function fetchIssueDetails()
    {
        if (empty($this->checksums)) {
            return [];
        } else {
            $db = $this->cell->notifications()->db();
            return $db->fetchAll(
                $db->select()->from('bem_issue')->where(
                    'checksum in (?)',
                    $this->checksums
                )
            );
        }
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
        // monitoring/host/show
        return $this::td(Link::create($row->host_name, 'bem/notification', [
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
        // monitoring/service/show
        return $this::td(Link::create($row->service_name, 'bem/notification', [
            'host'    => $row->host_name,
            'service' => $row->service_name
        ], ['class' => 'rowaction']))->addAttributes([
            'class' => $classes
        ]);
    }

    protected function prepareQuery()
    {
        if ($this->showOnlyProblems) {
            return $this->cell->selectProblemEvents();
        } else {
            return $this->cell->selectEvents();
        }
    }
}
