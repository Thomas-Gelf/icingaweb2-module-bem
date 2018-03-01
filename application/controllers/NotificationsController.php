<?php

namespace Icinga\Module\Bem\Controllers;

use dipl\Html\Table;
use Icinga\Module\Bem\BemIssueTable;

class NotificationsController extends ControllerBase
{
    public function init()
    {
        $this->prepareTabs();
    }

    public function indexAction()
    {
        $this->addTitle($this->translate('Notifications that have been sent'));

        $table = new Table();
        (BemIssueTable::forCell($this->requireCell()))->renderTo($this);
        $this->content()->add($table);
    }
}
