<?php

namespace Icinga\Module\Bem\Controllers;

use dipl\Html\Html;
use Icinga\Module\Bem\BemIssue;
use Icinga\Module\Bem\Web\Table\NotificationLogTable;
use Icinga\Module\Bem\Web\Widget\IssueDetails;

class IssueController extends ControllerBase
{
    /**
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function indexAction()
    {
        $this->setAutorefreshInterval(10);
        $issue = $this->loadIssue();
        $this->addIssueTabs($issue);
        if (! $issue || $issue->get('host_name') === null) {
            $this->content()->add(
                Html::tag('p', ['class' => 'error'], $this->translate(
                    'Issue not found, it might have recovered'
                ))
            );

            return;
        }
        $this->addTitle(
            '%s: %s',
            $issue->get('host_name'),
            $issue->get('object_name')
        );
        $this->content()->add(new IssueDetails($issue));
    }

    public function notificationsAction()
    {
        $this->setAutorefreshInterval(10);
        $issue = $this->loadIssue();
        $this->addTitle(
            'Notifications for %s: %s',
            $issue->get('host_name'),
            $issue->get('object_name')
        );
        $this->addIssueTabs($issue);
        $this->content()->add(
            NotificationLogTable::forCell($issue->getCell())
                ->filterIssue($issue)
        );
    }

    protected function addIssueTabs(BemIssue $issue)
    {
        $tabs = $this->tabs()->add('index', [
            'label'     => $this->translate('Issue'),
            'url'       => 'bem/issue',
            'urlParams' => $issue->getUrlParams()
        ]);

        if ($issue->get('cnt_notifications') > 0) {
            $tabs->add('notifications', [
                'label'     => $this->translate('Sent Notifications'),
                'url'       => 'bem/issue/notifications',
                'urlParams' => $issue->getUrlParams()
            ]);
        }

        $tabs->activate($this->getRequest()->getActionName());
    }

    protected function loadIssue()
    {
        return BemIssue::load(
            $this->requireCell(),
            $this->params->getRequired('host'),
            $this->params->getRequired('object')
        );
    }
}
