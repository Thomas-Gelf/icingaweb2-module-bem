<?php

namespace Icinga\Module\Bem\Controllers;

use Icinga\Module\Bem\BemNotification;
use Icinga\Module\Bem\Web\Widget\NotificationDetails;

class NotificationController extends ControllerBase
{
    public function indexAction()
    {
        $notification = BemNotification::loadFromLog(
            $this->requireCell(),
            $this->params->getRequired('id')
        );

        $this->addSingleTab($this->translate('Notification'));
        if ($notification) {
            $details = new NotificationDetails($notification);
            $this->content()->add($details);
        } else {
            $this->content()->add('No issue found');
        }
    }
}
