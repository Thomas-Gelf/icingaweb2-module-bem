<?php

namespace Icinga\Module\Bem\Controllers;

use Icinga\Module\Bem\ProblemsTable;

class IdoController extends ControllerBase
{
    public function init()
    {
        $this->prepareTabs();
    }

    public function indexAction()
    {
        $this->addTitle($this->translate('Hosts and Services for BEM'));
        $table = new ProblemsTable($this->db());
        $table->showOnlyProblems(false)->renderTo($this);
    }

    public function problemsAction()
    {
        $this->addTitle($this->translate('Problems for BEM'));
        $table = new ProblemsTable($this->db());
        $table->renderTo($this);
    }

    protected function prepareTabs()
    {
        $this->tabs()->add('problems', [
            'label' => $this->translate('Problems'),
            'url'   => 'bem/bem/problems'
        ])->add('index', [
            'label' => $this->translate('All Objects'),
            'url'   => 'bem/bem'
        ])->activate($this->getRequest()->getActionName());
    }
}
