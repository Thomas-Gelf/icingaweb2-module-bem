<?php

namespace Icinga\Module\Bem;

use Zend_Db_Adapter_Abstract as DbAdapter;
use Zend_Db_Expr as DbExpr;

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
     * @param Event $event
     */
    public function persistEventResult(Event $event)
    {
        if ($this->hasIssue($event->getObjectChecksum())) {
            $this->updateIssueFromEvent($event);
        } else {
            $this->createIssueFromEvent($event);
        }

        $this->logEvent($event);
    }

    protected function logEvent(Event $event)
    {
        $this->db()->insert('bem_notification_log', [
            'ci_name_checksum'  => $event->getObjectChecksum(),
            'notification_time' => time() * 1000,// time from Event,
            'exit_code'         => $event->getLastExitCode(),
            'command_line'      => $event->getLastCmdLine(),
            'output'            => $event->getLastOutput(),
        ]);
    }

    public function discardEvent(Event $event)
    {
        $this->db()->delete($this->getTableName(), $this->whereEvent($event));
        $this->issueCacheRemove($event->getObjectChecksum());
    }

    /**
     * @param Event $event
     */
    protected function createIssueFromEvent(Event $event)
    {
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
            'last_exit_code'     => $event->getLastExitCode(),
            'last_cmdline'       => $event->getLastCmdLine(),
            'last_output'        => $event->getLastOutput(),
        ];

        $this->db()->insert($this->getTableName(), $props);
        $this->issueCacheInsert($props);
    }

    /**
     * @param Event $event
     */
    protected function updateIssueFromEvent(Event $event)
    {
        $now = time();

        $props = [
            'bem_event_id'      => $event->getId(),
            'last_priority'     => $event->getPriority(),
            'last_severity'     => $event->getSeverity(),
            'last_notification' => $now,
            'next_notification' => $now + $this->getResendInterval(),
            'cnt_notifications' => new DbExpr('cnt_notifications + 1'),
            'last_exit_code'    => $event->getLastExitCode(),
            'last_cmdline'      => $event->getLastCmdLine(),
            'last_output'       => $event->getLastOutput(),
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
        $issues = [];
        $rows = $this->db->fetchAll($this->selectIssues());
        foreach ($rows as $row) {
            $issues[$row->checksum] = $row;
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
