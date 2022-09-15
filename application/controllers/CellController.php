<?php

namespace Icinga\Module\Bem\Controllers;

use gipfl\Web\Table\NameValueTable;
use Icinga\Date\DateFormatter;
use Icinga\Module\Bem\CellHealth;
use Icinga\Module\Bem\Config\CellConfig;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;

class CellController extends ControllerBase
{
    public function indexAction()
    {
        $this->setAutorefreshInterval(3);
        $cellName = $this->params->getRequired('name');
        $this->addSingleTab($this->translate('BEM Cell'));
        $this->addTitle($this->translate('BEM Cell "%s"'), $cellName);

        $cell = CellConfig::loadByName($cellName);

        $slotTable = new NameValueTable();
        foreach ($cell->getSection('msend_params') as $key => $value) {
            $slotTable->addNameValueRow($key, Html::tag('pre', null, $value));
        }

        $this->renderHealth($cell, $this->content());
        $this->content()->add([
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

    protected function renderHealth(CellConfig $cell, HtmlDocument $container)
    {
        $health = new CellHealth($cell);

        $info = $health->getInfo();
        if ($pid = $info->getPid()) {
            $container->add($this->createHint(Html::sprintf(
                $this->translate(
                    'BEM daemon for "%s" is running as PID %s by user %s on %s, last refresh happened %s'
                ),
                $cell->getName(),
                Html::tag('strong', (string) $pid),
                Html::tag('strong', $info->getUsername()),
                Html::tag('strong', $info->getFqdn()),
                $this->timeAgo($info->getLastUpdate() / 1000)
            ), 'information'));
        } else {
            if ($info->getFqdn()) {
                $container->add($this->createHint(Html::sprintf(
                    $this->translate(
                        'BEM daemon for "%s" is NOT running. It was last seen on %s %s'
                    ),
                    $cell->getName(),
                    Html::tag('strong', $info->getFqdn()),
                    $this->timeAgo($info->getLastUpdate() / 1000)
                )));

                return;
            } else {
                $container->add($this->createHint(
                    "The daemon for this cell has never been running"
                ));

                return;
            }
        }
        $table = new NameValueTable();
        $container->add($table);
        $table->addNameValuePairs([
            $this->translate('Processes') => sprintf(
                $this->translate('%d of %d allowed ImpactPoster (msend) processes'),
                $info->getRunningProcessCount(),
                $info->getMaxProcessCount()
            ),
            $this->translate('Queue Size') => sprintf(
                $this->translate('%d messages are waiting be sent'),
                $info->getQueueSize()
            ),
            $this->translate('Fail-Over') => $cell->hasFailOver()
                ? $this->translate('Yes')
                : $this->translate('No'),
        ]);

        if ($cell->hasFailOver()) {
            $table->addNameValuePairs([
                $this->translate('Configured Role') => $cell->getRole(),
                $this->translate('Effective Role') => $info->isMaster()
                    ? $this->translate('master')
                    : $this->translate('standby')
            ]);
        }
        $table->addNameValuePairs([
            $this->translate('Last Modification') => DateFormatter::formatDateTime($info->getLastModification() / 1000),
            $this->translate('Last Update') => DateFormatter::formatDateTime($info->getLastUpdate() / 1000),
            $this->translate('PHP Version') => $info->getPhpVersion(),
        ]);
    }

    protected function createHint($message, $class = 'error')
    {
        return Html::tag('p', [
            'class' => $class
        ], $message);
    }

    protected function timeAgo($time)
    {
        return Html::tag('span', [
            'class' => 'time-ago',
            'title' => DateFormatter::formatDateTime($time)
        ], DateFormatter::timeAgo($time));
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
