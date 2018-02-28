<?php

namespace Icinga\Module\Bem;

use DirectoryIterator;
use Icinga\Application\Icinga;
use Icinga\Exception\ConfigurationError;

class Config
{
    /** @var string */
    protected $configDir;

    public function getDefaultCellName()
    {
        $cells = $this->enumConfiguredCells();
        if (empty($cells)) {
            throw new ConfigurationError(
                'No cell has been configured in [ICINGAWEB_CONFIGDIR]/modules/bem/cells'
            );
        }

        return array_shift($cells);
    }

    /**
     * Whether a given cell configuration file name exists
     *
     * @param $name
     * @return bool
     */
    public function hasCell($name)
    {
        return array_key_exists($name, $this->enumConfiguredCells());
    }

    /**
     * @return array
     */
    public function listConfiguredCells()
    {
        return array_values($this->enumConfiguredCells());
    }

    /**
     * @return array
     */
    public function enumConfiguredCells()
    {
        $files = [];

        $dir = $this->getCellConfigDir();
        if (! is_dir($dir) || ! is_readable($dir)) {
            return [];
        }

        foreach (new DirectoryIterator($dir) as $file) {
            if ($file->isDot()) {
                continue;
            }

            $filename = $file->getFilename();
            if (substr($filename, -4) === '.ini') {
                $cellname = substr($filename, 0, -4);
                $files[$cellname] = $cellname;
            }
        }

        natcasesort($files);
        return $files;
    }

    /**
     * Configuration director containing cell information
     *
     * Usually /etc/icingaweb2/modules/bem/cells
     *
     * @return string
     */
    public function getCellConfigDir()
    {
        return $this->getConfigDir() . '/cells';
    }

    /**
     * @param $dir
     * @return $this
     */
    public function setConfigDir($dir)
    {
        $this->configDir = (string) $dir;
        return $this;
    }

    /**
     * @return string
     */
    public function getConfigDir()
    {
        if ($this->configDir === null) {
            $this->configDir = Icinga::app()
                ->getModuleManager()
                ->getModule('bem')
                ->getConfigDir();
        }

        return $this->configDir;
    }
}
