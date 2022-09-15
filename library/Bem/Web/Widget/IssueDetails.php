<?php

namespace Icinga\Module\Bem\Web\Widget;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Table\NameValueTable;
use Icinga\Date\DateFormatter;
use Icinga\Module\Bem\BemIssue;
use ipl\Html\Html;

class IssueDetails extends NameValueTable
{
    use TranslationHelper;

    protected $issue;

    protected $host;

    protected $service;

    /**
     * IssueDetails constructor.
     * @param BemIssue $issue
     * @throws \Icinga\Exception\IcingaException
     */
    public function __construct(BemIssue $issue)
    {
        $this->issue = $issue;
        list($this->host, $this->service) = BemIssue::splitCiName($issue->get('ci_name'));
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     */
    protected function assemble()
    {
        $i = $this->issue;
        $this->addNameValueRow($this->translate('Host'), $this->host);

        if ($this->service !== null) {
            $this->addNameValueRow($this->translate('Service'), $this->service);
        }
        $this->addNameValuePairs($i->getSlotSetValues());
        $this->addNameValuePairs([
            $this->translate('Notifications') => $i->get('cnt_notifications'),
            $this->translate('Next Notification') => new NextNotificationRenderer(
                $i->get('ts_next_notification')
            )
        ]);

        if ($i->get('cnt_notifications') > 0) {
            $this->addNameValuePairs([
                $this->translate('Last Notification') => $this->timeAgo(
                    $i->get('ts_last_notification')
                ),
                $this->translate('First Notification') => $this->timeAgo(
                    $i->get('ts_next_notification')
                )
            ]);
        }
        $this->addNameValuePairs([
            'Severity' => $i->get('severity'),
            'Worst Severity' => $i->get('worst_severity'),
        ]);
    }

    protected function timeAgo($timestamp)
    {
        return Html::tag(
            'span',
            [
                'class' => 'time-ago',
                'title' => DateFormatter::formatDateTime($timestamp / 1000)
            ],
            DateFormatter::timeAgo($timestamp / 1000)
        );
    }
}
