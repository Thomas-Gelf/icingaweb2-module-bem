<?php

namespace Icinga\Module\Bem\Controllers;

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

        (BemIssueTable::forCell($this->requireCell()))->renderTo($this);
    }

    protected function prepareTabs()
    {
        $this->tabs()->add('issues', [
            'label' => $this->translate('Current Issues'),
            'url'   => 'bem/issues'
        ])->add('notifications', [
            'label' => $this->translate('Sent Notifications'),
            'url'   => 'bem/notifications'
        ])->activate($this->getRequest()->getControllerName());
    }
}
