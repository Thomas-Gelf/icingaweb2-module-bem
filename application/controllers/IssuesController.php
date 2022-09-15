<?php

namespace Icinga\Module\Bem\Controllers;

use Icinga\Module\Bem\Web\Table\BemIssueTable;

class IssuesController extends ControllerBase
{
    public function init()
    {
        $this->prepareTabs();
    }

    public function indexAction()
    {
        $this->setAutorefreshInterval(10);
        $this->addTitle($this->translate('Current Issues'));

        $table = BemIssueTable::forCell($this->requireCell());
        if (! count($table)) {
            $this->content()->add(
                'Currently there are no pending issues'
            );

            return;
        }
        $table->renderTo($this);
    }
}
