<?php

namespace Icinga\Module\Bem;

use Exception;
use Icinga\Application\Platform;
use Icinga\Module\Bem\Config\CellConfig;
use Zend_Db_Adapter_Abstract as ZfDbAdapter;

class CellHealth
{
    /** @var CellInfo */
    protected $info;

    /** @var CellInfo */
    protected $otherInfo;

    /** @var CellConfig */
    protected $cell;

    public function __construct(CellConfig $cell)
    {
        $this->info = new CellInfo($cell);
        $this->otherInfo = new CellInfo($cell);
        $this->cell = $cell;
        $this->refresh();
    }

    public function shouldBeMaster()
    {
        $cell = $this->cell;
        if ($cell->shouldBeMaster() || ! $cell->hasFailOver()) {
            return true;
        }

        return ! $this->otherInfo->isRunning();
    }

    public function promote()
    {
        $this->setMaster(true);
    }

    public function demote()
    {
        $this->setMaster(false);
    }

    public function refresh()
    {
        $this->refreshInfo($this->info, $this->cell->db());
        $this->refreshInfo($this->otherInfo, $this->cell->otherDb());
    }

    /**
     * @param CellInfo $info
     * @param ZfDbAdapter|null $db
     */
    protected function refreshInfo(CellInfo $info, $db = null)
    {
        if ($db === null) {
            $info->setInfo(null);

            return;
        }

        try {
            $result = $db->fetchRow(
                $db->select()->from('bem_cell_stats')
                    ->where('cell_name = ?', $this->cell->getName())
            );
        } catch (Exception $e) {
            $result = null;
        }

        $info->setInfo($result);
    }

    public function refreshProcessInfo()
    {
        $db = $this->cell->db();
        $db->update('bem_cell_stats', [
            'pid'             => posix_getpid(),
            'fqdn'            => Platform::getFqdn(),
            'username'        => Platform::getPhpUser(),
            'php_version'     => Platform::getPhpVersion(),
        ], $this->renderWhere());
    }

    public function clearPid()
    {
        $db = $this->cell->db();
        $db->update('bem_cell_stats', [
            'pid' => null,
        ], $this->renderWhere());
    }

    protected function setMaster($isMaster = true)
    {
        $db = $this->cell->db();
        $db->update('bem_cell_stats', [
            'is_master' => $isMaster ? 'y' : 'n'
        ], $this->renderWhere());
        $this->refreshInfo($this->info, $this->cell->db());
    }

    protected function renderWhere()
    {
        // Hint: should not be used for otherDb (it currently isn't, so this is fine)
        return $this->cell->db()->quoteInto('cell_name = ?', $this->cell->getName());
    }

    public function getInfo()
    {
        return $this->info;
    }

    public function getOtherInfo()
    {
        return $this->otherInfo;
    }
}
