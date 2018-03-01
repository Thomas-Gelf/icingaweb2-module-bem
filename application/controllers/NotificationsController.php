<?php

namespace Icinga\Module\Bem\Controllers;

use dipl\Html\Table;
use Icinga\Module\Bem\BemIssueTable;
use Icinga\Module\Bem\Web\Table\NotificationLogTable;

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
        (NotificationLogTable::forCell($this->requireCell()))->renderTo($this);
        $this->content()->add($table);
    }
}
