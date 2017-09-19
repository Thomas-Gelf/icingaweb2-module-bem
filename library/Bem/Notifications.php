<?php

namespace Icinga\Module\Bem;

use Zend_Db_Adapter_Abstract as DbAdapter;
use Zend_Db_Expr as DbExpr;

/**
 * Class Issues
 *
 * Deals with refreshing/updating events in our DB
 */
class Notifications
{
    /** @var DbAdapter */
    private $db;

    /** @var |stdClass[] */
    private $issues;

    /** @var string TODO: Rename to bem_notification? */
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
     * @param ImpactPoster $poster
     */
    public function persistPosterResult(ImpactPoster $poster)
    {
        if ($this->hasIssue($poster->getEvent()->getObjectChecksum())) {
            $this->updateIssueFromMsendResult($poster);
        } else {
            $this->createIssueFromMsendResult($poster);
        }
    }

    public function discardEvent(Event $event)
    {
        $this->db()->delete($this->getTableName(), $this->whereEvent($event));
        $this->issueCacheRemove($event->getObjectChecksum());
    }

    /**
     * @param ImpactPoster $poster
     */
    protected function createIssueFromMsendResult(ImpactPoster $poster)
    {
        $event = $poster->getEvent();
        $now = time();
        $props = [
            'checksum'           => $event->getObjectChecksum(),
            'bem_event_id'       => $event->getId(),
            'host'               => $event->getHostName(),
            'service'            => $event->getServiceName(),
            'last_priority'      => $event->getPriority(),
            'last_severity'      => $event->getSeverity(),
            'first_notification' => $now,
            'last_notification'  => $now,
            'next_notification'  => $now + $this->getResendInterval(),
            'cnt_notifications'  => 1,
            'last_exit_code'     => $poster->getLastExitCode(),
            'last_cmdline'       => $poster->getCommandString(),
            'last_output'        => $poster->getLastOutput(),
        ];

        $this->db()->insert($this->getTableName(), $props);
        $this->issueCacheInsert($props);
    }

    /**
     * @param ImpactPoster $poster
     */
    protected function updateIssueFromMsendResult(ImpactPoster $poster)
    {
        $event = $poster->getEvent();
        $now = time();

        $props = [
            'bem_event_id'      => $event->getId(),
            'last_priority'     => $event->getPriority(),
            'last_severity'     => $event->getSeverity(),
            'last_notification' => $now,
            'next_notification' => $now + $this->getResendInterval(),
            'cnt_notifications' => new DbExpr('cnt_notifications + 1'),
            'last_exit_code'    => $poster->getLastExitCode(),
            'last_cmdline'      => $poster->getCommandString(),
            'last_output'       => $poster->getLastOutput(),
        ];

        $this->db()->update($this->getTableName(), $props, $this->whereEvent($event));
        $this->issueCacheUpdate($event->getObjectChecksum(), $props);
    }

    /**
     * @return |stdClass[]
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
     * Fetches all existing issues from our DB
     *
     * @return \stdClass[]
     */
    public function fetchExistingIssues()
    {
        $db = $this->db;
        $issues = [];
        $rows = $db->fetchAll($db->select()->from($this->getTableName()));
        foreach ($rows as $row) {
            $issues[$row->checksum] = $row;
        }

        return $issues;
    }

    /**
     * @return DbAdapter
     */
    public function db()
    {
        return $this->db;
    }

    /**
     * @return int
     */
    protected function getResendInterval()
    {
        return 300;
    }

    /**
     * @param Event $event
     * @return string
     */
    protected function whereEvent(Event $event)
    {
        return $this->db()->quoteInto(
            'checksum = ?',
            $event->getObjectChecksum()
        );
    }

    /**
     * @param array $props
     */
    protected function issueCacheInsert(array $props)
    {
        $this->issues[$props['checksum']] = (object) $props;
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
