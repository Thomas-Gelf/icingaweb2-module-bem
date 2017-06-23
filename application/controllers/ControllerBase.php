<?php

namespace Icinga\Module\Bem\Controllers;

use Icinga\Module\Bem\Config;
use ipl\Web\CompatController;

class ControllerBase extends CompatController
{
    private $db;

    /**
     * @return \Zend_Db_Adapter_Abstract
     */
    protected function db()
    {
        if ($this->db === null) {
            $config = new Config();
            $cell = $this->params->get('cell');
            if ($cell === null) {
                $cell = $config->getDefaultCellName();
                $this->redirectNow($this->url()->with('cell', $cell));
            }

            $this->db = $config->getCell($this->params->getRequired('cell'))->getIdo();
        }

        return $this->db;
    }
}
