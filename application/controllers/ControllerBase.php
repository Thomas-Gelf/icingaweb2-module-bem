<?php

namespace Icinga\Module\Bem\Controllers;

use Icinga\Module\Bem\Cell;
use Icinga\Module\Bem\Config;
use dipl\Web\CompatController;

class ControllerBase extends CompatController
{
    /** @var \Zend_Db_Adapter_Abstract */
    private $idoDb;

    /** @var Cell */
    private $cell;

    /**
     * @return \Zend_Db_Adapter_Abstract
     */
    protected function idoDb()
    {
        if ($this->idoDb === null) {
            $this->idoDb = $this->requireCell()->getIdo()->getDb();
        }

        return $this->idoDb;
    }

    protected function requireCell()
    {
        if ($this->cell === null) {
            $config = new Config();
            $cell = $this->params->get('cell');
            if ($cell === null) {
                $cell = $config->getDefaultCellName();
                $this->redirectNow($this->url()->with('cell', $cell));
            }

            $this->cell = $config->getCell($cell);
        }

        return $this->cell;
    }
}
