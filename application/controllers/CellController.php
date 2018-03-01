<?php

namespace Icinga\Module\Bem\Controllers;

use dipl\Html\Html;
use dipl\Web\Widget\NameValueTable;
use Icinga\Module\Bem\CellStats;
use Icinga\Module\Bem\Config\CellConfig;

class CellController extends ControllerBase
{
    public function indexAction()
    {
        $cellName = $this->params->getRequired('name');
        $this->addSingleTab($this->translate('BEM Cell'));
        $this->addTitle($this->translate('BEM Cell "%s"'), $cellName);

        $cell = CellConfig::loadByName($cellName);

        $slotTable = new NameValueTable();
        foreach ($cell->getSection('msend_params') as $key => $value) {
            $slotTable->addNameValueRow($key, Html::tag('pre', null, $value));
        }

        $this->content()->add([
            Html::tag('h2', null, $this->translate('Runner Health')),
            $this->renderHealth($cell),
            Html::tag('h2', null, $this->translate('Slot Values')),
            $slotTable,
            Html::tag('h2', null, $this->translate('Used Params')),
            $this->renderList($cell->getUsedVarNames()),
            Html::tag('h2', null, $this->translate('Whitelists')),
            $this->renderList($cell->getSection('whitelist')),
            Html::tag('h2', null, $this->translate('Blacklists')),
            $this->renderList($cell->getSection('blacklist')),
        ]);
    }

    protected function renderHealth(CellConfig $cell)
    {
        if (! CellStats::exist($cell)) {
            return Html::tag(
                'p',
                ['class' => 'error'],
                "This cell has not yet stored any stats, please check whether it's daemon is running"
            );
        }
    }

    protected function renderList($list)
    {
        $strings = [];
        foreach ($list as $entry) {
            $strings[] = (string) $entry;
        }
        $pre = Html::tag('pre', null, implode("\n", $strings));

        return $pre;
    }
}
