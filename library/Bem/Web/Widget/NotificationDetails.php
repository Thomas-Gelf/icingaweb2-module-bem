<?php

namespace Icinga\Module\Bem\Web\Widget;

use dipl\Html\Html;
use dipl\Translation\TranslationHelper;
use dipl\Web\Widget\NameValueTable;
use Icinga\Module\Bem\BemIssue;
use Icinga\Module\Bem\BemNotification;
use Icinga\Module\Bem\Process\ImpactPosterExitCodes;

class NotificationDetails extends NameValueTable
{
    use TranslationHelper;

    protected $notification;

    protected $host;

    protected $service;

    /**
     * NotificationDetails constructor.
     * @param BemNotification $notification
     * @throws \Icinga\Exception\IcingaException
     */
    public function __construct(BemNotification $notification)
    {
        $this->notification = $notification;
        list($this->host, $this->service) = BemIssue::splitCiName($notification->get('ci_name'));
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     */
    protected function assemble()
    {
        $n = $this->notification;
        $this->addNameValueRow($this->translate('Host'), $this->host);

        if ($this->service !== null) {
            $this->addNameValueRow($this->translate('Service'), $this->service);
        }
        $this->addNameValuePairs($n->getSlotSetValues());
        $exitCodeInfo = new ImpactPosterExitCodes();
        if ($n->get('command_line') !== null) {
            $this->addNameValueRow(
                $this->translate('Command-line'),
                Html::tag('pre', null, preg_replace(
                    '/\s(-[a-zA-Z])\s/',
                    " \\\n\\1 ",
                    $n->get('command_line')
                ))
            );

            $exitCode = $n->get('exit_code');
            if ($exitCode !== null) {
                $exitCode = (int) $exitCode;
                $this->addNameValuePairs([
                    $this->translate('Exit code') => sprintf(
                        '%d - %s',
                        $exitCode,
                        $exitCodeInfo->getExitCodeDescription($exitCode)
                    ),
                    $this->translate('Last output') => Html::tag('pre', null, $n->get('output')),
                ]);
            }
        }
    }
}
