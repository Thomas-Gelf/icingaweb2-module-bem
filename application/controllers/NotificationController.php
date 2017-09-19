<?php

namespace Icinga\Module\Bem\Controllers;

use ipl\Html\Html;

class NotificationController extends ControllerBase
{
    public function indexAction()
    {
        if ($service = $this->params->get('service')) {
            $checksum = sha1(
                $this->params->get('host')
                . $this->params->get('service'),
                true
            );
        } else {
            $checksum = sha1(
                $this->params->get('host'),
                true
            );
        }
        $notification = $this->requireCell()
            ->notifications()
            ->loadByChecksum($checksum);

        $this->addSingleTab($this->translate('Notification'))
            ->addTitle($this->translate('BEM Notification'));

        $this->content()
            ->add(Html::pre(print_r($notification, 1)));
        if (property_exists($notification, 'last_cmdline')) {
            $this->content()->add(Html::pre($notification->last_cmdline));
        } else {
            die('adsf');
        }
    }
}
