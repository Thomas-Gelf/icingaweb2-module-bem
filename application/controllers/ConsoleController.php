<?php

namespace Icinga\Module\Bem\Controllers;

use ipl\Html\Html as h;

class ConsoleController extends ControllerBase
{
    public function init()
    {
        $this->prepareTabs();
    }

    public function treeAction()
    {
        $this->addTitle($this->translate('BMC Console'));

        $summaries = [];
        $varnames = [
            'environment'   => 'ENVIRONMENT',
            'bmc_object_class' => 'TEAM',
            'contact_team'     => 'OS',
        ];

        foreach ($varnames as $varname => $label) {
            $summaries[$label] = $this->mergeSummaries(
                $this->fetchHostSummaries($varname),
                $this->fetchServiceSummaries($varname)
            );
        }

        $c = $this->content();
        foreach ($summaries as $label => $summary) {
            $c->add(h::h2($label));
            foreach ($summary as $key => $cnt) {
                $c->add(sprintf(
                    '%s: %d hosts, %d services',
                    $key,
                    $cnt->cnt_hosts,
                    $cnt->cnt_services
                ))->add(h::br());
            }
        }
    }

    protected function prepareTabs()
    {
        $this->tabs()->add('problems', [
            'label' => $this->translate('Problems'),
            'url'   => 'bem/bem/problems'
        ])->add('tree', [
            'label' => $this->translate('Problem Tree'),
            'url'   => 'bem/bem/console'
        ])->add('index', [
            'label' => $this->translate('All Objects'),
            'url'   => 'bem/bem'
        ])->activate($this->getRequest()->getActionName());
    }

    protected function mergeSummaries($first, $second)
    {
        foreach ($first as $key => $values) {
            if (array_key_exists($key, $second)) {
                $row = $second[$key];
            } else {
                $row = $this->emptyServiceSummaryRow();
            }
            foreach ((array) $row as $p => $v) {
                $first[$key]->$p = $v;
            }
        }

        return $first;
    }

    protected function indexByLabel($result)
    {
        $indexed = [];
        foreach ($result as $row) {
            $key = $row->label;
            unset($row->label);
            $indexed[$key] = $row;
        }

        return $indexed;
    }

    protected function fetchHostSummaries($varname)
    {
        $db = $this->db();
        $columns = [
            'label'           => "CASE WHEN hcv.varvalue IS NULL THEN '[UNKNOWN]' ELSE hcv.varvalue END",
            'cnt_hosts'       => 'COUNT(DISTINCT ho.object_id)',
            'cnt_up'          => 'SUM(CASE WHEN hs.current_state = 0 THEN 1 ELSE 0 END)',
            'cnt_down'        => 'SUM(CASE WHEN hs.current_state = 1 THEN 1 ELSE 0 END)',
            // 'cnt_unreachable' => 'SUM(CASE WHEN hs.current_state = 2 THEN 1 ELSE 0 END)',
            // 'cnt_pending'     => 'SUM(CASE WHEN hs.current_state IS NULL OR hs.has_been_checked = 0 THEN 1 ELSE 0 END)',
            //'cnt_unknown'     => 'SUM(CASE WHEN hcv.object_id IS NULL THEN 1 ELSE 0 END)',
        ];

        return $this->indexByLabel(
            $db->fetchAll($this->prepareHostSummariesQuery($varname)->columns($columns))
        );
    }

    protected function emptyServiceSummaryRow()
    {
        return (object) [
            'cnt_services' => 0,
            'cnt_ok'       => 0,
            'cnt_warning'  => 0,
            'cnt_critical' => 0,
            'cnt_unknown'  => 0,
        ];
    }

    protected function fetchServiceSummaries($varname)
    {
        $db = $this->db();
        $columns = [
            'label'        => "CASE WHEN hcv.varvalue IS NULL THEN '[UNKNOWN]' ELSE hcv.varvalue END",
            'cnt_services' => 'COUNT(DISTINCT so.object_id)',
            'cnt_ok'       => 'SUM(CASE WHEN ss.current_state = 0 THEN 1 ELSE 0 END)',
            'cnt_warning'  => 'SUM(CASE WHEN ss.current_state = 1 THEN 1 ELSE 0 END)',
            'cnt_critical' => 'SUM(CASE WHEN ss.current_state = 2 THEN 1 ELSE 0 END)',
            'cnt_unknown'  => 'SUM(CASE WHEN ss.current_state = 3 THEN 1 ELSE 0 END)',
            //'cnt_unknown'     => 'SUM(CASE WHEN hcv.object_id IS NULL THEN 1 ELSE 0 END)',
        ];

        return $this->indexByLabel(
            $db->fetchAll($this->prepareServiceSummariesQuery($varname)->columns($columns))
        );
    }

    protected function prepareHostSummariesQuery($varname)
    {
        $db = $this->db();
        return $db->select()->from(
            ['h' => 'icinga_hosts'],
            []
        )->join(
            ['ho' => 'icinga_objects'],
            'h.host_object_id = ho.object_id AND ho.is_active = 1',
            []
        )->joinLeft(
            ['hs' => 'icinga_hoststatus'],
            'ho.object_id = hs.host_object_id',
            []
        )->joinLeft(
            ['hcv' => 'icinga_customvariablestatus'],
            $db->quoteInto(
                'ho.object_id = hcv.object_id AND hcv.varname = ?',
                $varname
            ),
            []
        )->group('label')->order('hcv.varvalue');
    }

    protected function prepareServiceSummariesQuery($varname)
    {
        $db = $this->db();
        return $db->select()->from(
            ['h' => 'icinga_hosts'],
            []
        )->join(
            ['ho' => 'icinga_objects'],
            'h.host_object_id = ho.object_id AND ho.is_active = 1',
            []
        )->join(
            ['s' => 'icinga_services'],
            'h.host_object_id = s.host_object_id',
            []
        )->join(
            ['so' => 'icinga_objects'],
            's.service_object_id = so.object_id AND so.is_active = 1',
            []
        )->joinLeft(
            ['ss' => 'icinga_servicestatus'],
            'so.object_id = ss.service_object_id',
            []
        )->joinLeft(
            ['hcv' => 'icinga_customvariablestatus'],
            $db->quoteInto(
                'ho.object_id = hcv.object_id AND hcv.varname = ?',
                $varname
            ),
            []
        )->group('label')->order('hcv.varvalue');
    }
}
