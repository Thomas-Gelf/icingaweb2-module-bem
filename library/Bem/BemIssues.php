<?php

namespace Icinga\Module\Bem;

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

    /** @var \stdClass[] */
    private $issues;

    /** @var string */
    protected $tableName = 'bem_issue';

    /**
     * BemIssues constructor.
     * @param DbAdapter $db
     */
    public function __construct(DbAdapter $db)
    {
        $this->db = $db;
    }

    public function loadByChecksum($checksum)
    {
        return $this->db->fetchRow(
            $this->db->select()
                ->from($this->tableName)
                ->where('checksum = ?', $checksum)
        );
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @return \stdClass[]
     */
    public function issues()
    {
        if ($this->issues === null) {
            $this->refreshIssues();
        }

        return $this->issues;
    }

    /**
     * @param string $checksum Binary checksum
     * @return bool
     */
    public function hasIssue($checksum)
    {
        return array_key_exists($checksum, $this->issues());
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
     * @return BemIssue[]
     */
    public function fetchOverdueIssues()
    {
        $now = Util::timestampWithMilliseconds();
        $query = $this->db->select()
            ->from('bem_issue')
            ->where('ts_next_notification < ?', $now);

        // TODO: create objects
        return $this->db->fetchAll($query);
    }

    /**
     * Fetches all existing issues from our DB
     *
     * @return \stdClass[]
     */
    public function fetchExistingIssues()
    {
        $issues = [];
        $rows = $this->db->fetchAll($this->selectIssues());
        foreach ($rows as $row) {
            $issues[$row->ci_name_checksum] = $row;
        }

        return $issues;
    }

    public function selectIssues()
    {
        return $this->db->select()->from($this->getTableName());
    }

    /**
     * @return DbAdapter
     */
    public function db()
    {
        return $this->db;
    }

    /**
     * @param array $props
     */
    protected function issueCacheInsert(array $props)
    {
        $this->issues[$props['ci_name_checksum']] = (object) $props;
    }

    /**
     * @param string $checksum
     * @param array $props
     */
    protected function issueCacheUpdate($checksum, array $props)
    {
        unset($props['cnt_notifications']);
        $issue = $this->issues[$checksum];
        foreach ($props as $key => $val) {
            $issue->$key = $val;
        }

        $issue->cnt_notifications++;
    }

    /**
     * @param string $checksum
     */
    protected function issueCacheRemove($checksum)
    {
        unset($this->issues[$checksum]);
    }
}
