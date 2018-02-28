<?php

namespace Icinga\Module\Bem\Web\Widget;

use dipl\Html\Html;
use dipl\Translation\TranslationHelper;
use dipl\Web\Widget\NameValueTable;
use Icinga\Module\Bem\Process\ImpactPosterExitCodes;

class NotificationDetails extends NameValueTable
{
    use TranslationHelper;

    protected $notification;

    protected $host;

    protected $service;

    public function __construct($notification, $host, $service = null)
    {
        $this->notification = $notification;
        $this->host = $host;
        $this->service = $service;
    }

    protected function assemble()
    {
        $n = $this->notification;
        $this->addNameValueRow($this->translate('Host'), $this->host);

        if ($this->service !== null) {
            $this->addNameValueRow($this->translate('Service'), $this->service);
        }
        $this->addNameValuePairs([
            $this->translate('Last priority') => $n->last_priority,
            $this->translate('Last severity') => $n->last_severity,
            $this->translate('Notifications') => $n->cnt_notifications,
            $this->translate('First notification') => $n->first_notification,
            $this->translate('Last notification') => $n->last_notification,
            $this->translate('Next notification') => $n->next_notification,
        ]);

        $exitCodeInfo = new ImpactPosterExitCodes();
        if ($n->last_cmdline !== null) {
            $this->addNameValueRow(
                $this->translate('Last command-line'),
                Html::tag('pre', null, preg_replace(
                    '/\s(-[a-zA-Z])\s/',
                    "\n\\1 ",
                    $n->last_cmdline
                ))
            );

            $exitCode = $n->last_exit_code;
            if ($exitCode !== null) {
                $exitCode = (int) $exitCode;
                $this->addNameValuePairs([
                    $this->translate('Last exit code') => sprintf(
                        '%d - %s',
                        $exitCode,
                        $exitCodeInfo->getExitCodeDescription($exitCode)
                    ),
                    $this->translate('Last output') => Html::tag('pre', null, $n->last_output),
                ]);
            }
        }
    }
}
