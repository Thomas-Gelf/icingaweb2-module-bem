<?php

namespace Icinga\Module\Bem\Controllers;

use Icinga\Module\Bem\Cell;
use Icinga\Module\Bem\Config;
use ipl\Web\CompatController;

class ControllerBase extends CompatController
{
    /** @var \Zend_Db_Adapter_Abstract */
    private $db;

    /** @var Cell */
    private $cell;

    /**
     * @return \Zend_Db_Adapter_Abstract
     */
    protected function db()
    {
        if ($this->db === null) {
            $this->db = $this->requireCell()->getIdo()->getDb();
        }

        return $this->db;
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
