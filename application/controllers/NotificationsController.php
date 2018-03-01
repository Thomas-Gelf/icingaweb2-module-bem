<?php

namespace Icinga\Module\Bem\Controllers;

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
        NotificationLogTable::forCell($this->requireCell())->renderTo($this);
    }
}
