<?php

namespace Icinga\Module\Bem\Controllers;

use Icinga\Module\Bem\Web\Widget\NotificationDetails;

class NotificationController extends ControllerBase
{
    public function indexAction()
    {
        if ($service = $this->params->get('service')) {
            $host = $this->params->get('host');
            $checksum = sha1($host . $service, true);
            $this->addTitle('%s: %s', $host, $service);
        } else {
            $host = $this->params->get('host');
            $service = null;
            $checksum = sha1($host, true);
            $this->addTitle($host);
        }
        $notification = $this->requireCell()
            ->notifications()
            ->loadByChecksum($checksum);

        $this->addSingleTab($this->translate('Notification'));
        if ($notification) {
            $details = new NotificationDetails($notification, $host, $service);
            $this->content()->add($details);
        } else {
            $this->content()->add('No issue found');
        }
    }
}
