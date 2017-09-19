<?php

namespace Icinga\Module\Bem\Controllers;

use ipl\Html\Html;
use ipl\Web\Widget\NameValueTable;

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
            $details = new NameValueTable();
            $details->addNameValueRow($this->translate('Host'), $host);

            if ($service !== null) {
                $details->addNameValueRow($this->translate('Service'), $service);
            }
            $details->addNameValuePairs([
                $this->translate('Last priority') => $notification->last_priority,
                $this->translate('Last severity') => $notification->last_severity,
                $this->translate('Notifications') => $notification->cnt_notifications,
                $this->translate('First notification') => $notification->first_notification,
                $this->translate('Last notification') => $notification->last_notification,
                $this->translate('Next notification') => $notification->next_notification,
            ]);

            if ($notification->last_cmdline !== null) {
                $details->addNameValueRow(
                    $this->translate('Last command-line'),
                    Html::pre($notification->last_cmdline)
                );

                $exitCode = $notification->last_exit_code;
                if ($exitCode !== null) {
                    $exitCode = (int) $exitCode;
                    $details->addNameValuePairs([
                        $this->translate('Last exit code') => $exitCode,
                        $this->translate('Last output') => Html::pre($notification->last_output),
                    ]);
                }
            }
            $this->content()->add($details);
        } else {
            $this->content()->add('No issue found');
        }
    }
}
