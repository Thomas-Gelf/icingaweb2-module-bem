<?php

namespace Icinga\Module\Bem\Controllers;

use dipl\Web\CompatController;
use Icinga\Module\Bem\Config;
use Icinga\Module\Bem\Config\CellConfig;
use Icinga\Module\Bem\IdoDb;

class ControllerBase extends CompatController
{
    /** @var \Zend_Db_Adapter_Abstract */
    private $idoDb;

    /** @var CellConfig */
    private $cell;

    protected function prepareTabs()
    {
        $this->tabs()->add('index', [
            'label' => $this->translate('Configured Cells'),
            'url'   => 'bem'
        ])->add('issues', [
            'label' => $this->translate('Current Issues'),
            'url'   => 'bem/issues'
        ])->add('notifications', [
            'label' => $this->translate('Sent Notifications'),
            'url'   => 'bem/notifications'
        ])->activate($this->getRequest()->getControllerName());
    }

    /**
     * @return \Zend_Db_Adapter_Abstract
     */
    protected function idoDb()
    {
        if ($this->idoDb === null) {
            $this->idoDb = IdoDb::fromMonitoringModule();
        }

        return $this->idoDb;
    }

    protected function requireCell()
    {
        if ($this->cell === null) {
            $name = $this->params->get('cell');

            if ($name === null) {
                $config = new Config();
                $name = $config->getDefaultCellName();
                $this->redirectNow($this->url()->with('cell', $name));
            }

            $this->cell = CellConfig::loadByName($name);
        }

        return $this->cell;
    }
}
