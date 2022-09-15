<?php

namespace Icinga\Module\Bem\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Icinga\Date\DateFormatter;
use Icinga\Module\Bem\BemIssue;
use Icinga\Module\Bem\Config\CellConfig;

class NotificationLogTable extends ZfQueryBasedTable
{
    /** @var CellConfig */
    private $cell;

    protected $defaultAttributes = [
        'class' => ['common-table', 'table-row-selectable'],
        'data-base-target' => '_next'
    ];

    protected $searchColumns = [
        'ci_name',
    ];

    /**
     * @param CellConfig $cell
     * @return static
     * @throws \Icinga\Exception\ProgrammingError
     */
    public static function forCell(CellConfig $cell)
    {
        $self = new static($cell->db());

        return $self->setCell($cell);
    }

    public function setCell(CellConfig $cell)
    {
        $this->cell = $cell;

        return $this;
    }

    /**
     * Well... it's the CI, not the issue
     *
     * @param BemIssue $issue
     * @return $this;
     * @throws \Icinga\Exception\IcingaException
     */
    public function filterIssue(BemIssue $issue)
    {
        $this->getQuery()->where('ci_name_checksum = ?', $issue->getKey());

        return $this;
    }

    protected function renderObjectName($ciName)
    {
        if (strpos($ciName, '!') === false) {
            return $ciName;
        } else {
            return implode(': ', preg_split('/!/', $ciName, 2));
        }
    }

    public function renderRow($row)
    {
        $ts = $row->ts_notification / 1000;
        $this->splitByDay($ts);

        return $this::row([
            DateFormatter::formatTime($ts),
            Link::create(
                BemIssue::makeNiceCiName($row->ci_name),
                'bem/notification',
                ['id' => $row->id, 'cell' => $this->cell->getName()]
            )
        ]);
    }

    protected function prepareQuery()
    {
        return $this->cell->db()->select()->from('bem_notification_log', [
            'id',
            'ts_notification',
            'ci_name',
            'severity',
        ])->order('ts_notification DESC');
    }
}
