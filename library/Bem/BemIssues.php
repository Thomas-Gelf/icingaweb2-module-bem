<?php

namespace Icinga\Module\Bem;

use Icinga\Application\Logger;
use Icinga\Module\Bem\Config\CellConfig;
use Zend_Db_Adapter_Abstract as DbAdapter;

/**
 * Class Issues
 *
 * Deals with refreshing/updating events in our DB
 */
class BemIssues
{
    /** @var DbAdapter */
    private $db;

    protected $cell;

    /** @var BemIssue[] */
    private $issues;

    /** @var string */
    protected $tableName = 'bem_issue';

    /**
     * BemIssues constructor.
     * @param CellConfig $cell
     */
    public function __construct(CellConfig $cell)
    {
        $this->cell = $cell;
        $this->db = $cell->db();
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @return BemIssue[]
     */
    public function issues()
    {
        if ($this->issues === null) {
            $this->refreshIssues();
        }

        return $this->issues;
    }

    /**
     * @param IdoDb $ido
     * @throws \Icinga\Exception\IcingaException
     * @throws \Zend_Db_Adapter_Exception
     */
    public function refreshFromIdo(IdoDb $ido)
    {
        $seen = [];
        // Make sure we loaded our issues
        $this->issues();
        foreach ($ido->fetchIssues($this->cell) as $issue) {
            $relevant = $issue->isRelevant();
            if ($this->has($issue)) {
                if ($issue->isNew()) {
                    if (! $relevant) {
                        continue;
                    }
                    $issue->scheduleNextNotification();
                }
                $knownIssue = $this->getWithChecksum($issue->getKey());
                if ($issue->get('severity') !== $knownIssue->get('severity')) {
                    $issue->scheduleNextNotification();
                }
                if (! $relevant) {
                    $issue->delete();
                }
                $seen[] = $issue->getKey();
            } elseif ($relevant) {
                $issue->scheduleNextNotification();
                $this->add($issue);
                $seen[] = $issue->getKey();
            }
        }

        $obsolete = array_diff(array_keys($this->issues), $seen);
        Logger::debug(
            "%d are obsolete, %d seen, %d total\n",
            count($obsolete),
            count($seen),
            count($this->issues)
        );

        foreach ($obsolete as $key) {
            // TODO: mark as obsolete, so we could send one last notification
            $this->delete($this->issues[$key]);
        }
    }

    /**
     * @param BemIssue $issue
     * @return bool
     * @throws \Icinga\Exception\IcingaException
     */
    public function has(BemIssue $issue)
    {
        return array_key_exists($issue->get('ci_name_checksum'), $this->issues());
    }

    /**
     * @param string $checksum
     * @return BemIssue
     */
    public function getWithChecksum($checksum)
    {
        return $this->issues[$checksum];
    }

    /**
     * @param BemIssue $issue
     * @return $this
     * @throws \Icinga\Exception\IcingaException
     * @throws \Zend_Db_Adapter_Exception
     */
    public function add(BemIssue $issue)
    {
        if ($this->has($issue)) {
            $existing = $this->issues[$issue->get('ci_name_checksum')];
            $existing->setProperties(
                $issue->getProperties()
            );

            if ($existing->hasBeenModified()) {
                $existing->store();
            }
        } else {
            $this->issues[$issue->get('ci_name_checksum')] = $issue;
            if ($issue->hasBeenModified()) {
                $issue->store();
            }
        }

        return $this;
    }

    /**
     * @param BemIssue $issue
     * @return $this
     * @throws \Icinga\Exception\IcingaException
     */
    public function delete(BemIssue $issue)
    {
        $issue->delete();
        if ($this->has($issue)) {
            unset($this->issues[$issue->getKey()]);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function refreshIssues()
    {
        $this->issues = $this->fetchExistingIssues();

        return $this;
    }

    /**
     * All issues that should be sent within the next minute
     *
     * @return BemIssue[]
     * @throws \Icinga\Exception\IcingaException
     */
    public function getDueIssues()
    {
        $due = [];
        $dueTime = Util::timestampWithMilliseconds() + 60 * 1000;
        foreach ($this->issues() as $issue) {
            if ($issue->isDueIn($dueTime)) {
                $due[] = $issue;
            }
        }

        Logger::debug(
            'Issue list contains %d issues, %d are due',
            count($this->issues),
            count($due)
        );

        return $due;
    }

    /**
     * Fetches all existing issues from our DB
     *
     * @return BemIssue[]
     */
    protected function fetchExistingIssues()
    {
        $issues = [];
        $rows = $this->db->fetchAll($this->selectIssues());
        foreach ($rows as $row) {
            $issues[$row->ci_name_checksum] = BemIssue::forDbRow($row, $this->cell);
        }

        return $issues;
    }

    protected function selectIssues()
    {
        return $this->db->select()->from($this->getTableName());
    }
}
