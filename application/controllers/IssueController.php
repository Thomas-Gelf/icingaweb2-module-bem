<?php

namespace Icinga\Module\Bem\Controllers;

use Icinga\Module\Bem\BemIssue;
use Icinga\Module\Bem\Web\Table\NotificationLogTable;
use Icinga\Module\Bem\Web\Widget\IssueDetails;
use ipl\Html\Html;

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
        $this->addTitle($issue->getNiceName());
        $this->content()->add(new IssueDetails($issue));
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function notificationsAction()
    {
        $this->setAutorefreshInterval(10);
        $issue = $this->loadIssue();
        $this->addTitle(
            'Notifications for %s',
            $issue->getNiceName()
        );
        $this->addIssueTabs($issue);
        $this->content()->add(
            NotificationLogTable::forCell($issue->getCell())
                ->filterIssue($issue)
        );
    }

    /**
     * @param BemIssue $issue
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
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

    /**
     * @return BemIssue
     * @throws \Icinga\Exception\MissingParameterException
     */
    protected function loadIssue()
    {
        return BemIssue::load(
            $this->requireCell(),
            $this->params->getRequired('host'),
            $this->params->get('service')
        );
    }
}
