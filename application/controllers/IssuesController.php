<?php

namespace Icinga\Module\Bem\Controllers;

use dipl\Html\Table;
use Icinga\Module\Bem\Web\Table\BemIssueTable;

class IssuesController extends ControllerBase
{
    public function init()
    {
        $this->prepareTabs();
    }

    public function indexAction()
    {
        $this->addTitle($this->translate('Current Issues'));

        $table = new Table();
        (BemIssueTable::forCell($this->requireCell()))->renderTo($this);
        $this->content()->add($table);
    }
}
