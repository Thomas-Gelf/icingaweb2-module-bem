<?php

namespace Icinga\Module\Bem\Web\Widget;

use gipfl\Translation\TranslationHelper;
use Icinga\Date\DateFormatter;
use Icinga\Module\Bem\Util;
use ipl\Html\BaseHtmlElement;

class NextNotificationRenderer extends BaseHtmlElement
{
    use TranslationHelper;

    protected $timestamp;

    protected $tag = 'span';

    public function __construct($timestamp)
    {
        $this->timestamp = $timestamp;
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     */
    protected function assemble()
    {
        if (Util::timestampWithMilliseconds() < $this->timestamp) {
            $this->setAttributes([
                'class' => 'time-until',
                'title' => DateFormatter::formatDateTime($this->timestamp / 1000),
            ])->add(
                DateFormatter::timeUntil($this->timestamp / 1000)
            );
        } else {
            $this->setAttributes([
                'class' => 'error time-ago',
                'title' => DateFormatter::formatDateTime($this->timestamp / 1000),
            ])->add(
                DateFormatter::timeAgo($this->timestamp / 1000)
            );
        }
    }
}
