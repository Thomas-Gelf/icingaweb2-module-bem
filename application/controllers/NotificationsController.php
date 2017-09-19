<?php

namespace Icinga\Module\Bem\Controllers;

use Icinga\Module\Bem\ProblemsTable;

class NotificationsController extends ControllerBase
{
    public function init()
    {
        $this->prepareTabs();
    }

    public function indexAction()
    {
        $this->addTitle($this->translate('Problems for BEM'));
        $table = (new ProblemsTable($this->db()))
            ->setCell($this->requireCell());
        $table->renderTo($this);
    }

    public function allAction()
    {
        $this->addTitle($this->translate('Hosts and Services for BEM'));

        $table = (new ProblemsTable($this->db()))
            ->setCell($this->requireCell());
        $table->showOnlyProblems(false)->renderTo($this);
    }

    protected function prepareTabs()
    {
        $this->tabs()->add('index', [
            'label' => $this->translate('Problems'),
            'url'   => 'bem/notifications'
        ])->add('all', [
            'label' => $this->translate('All Objects'),
            'url'   => 'bem/notifications/all'
        ])->activate($this->getRequest()->getActionName());
    }
}
