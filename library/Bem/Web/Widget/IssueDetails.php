<?php

namespace Icinga\Module\Bem\Web\Widget;

use dipl\Translation\TranslationHelper;
use dipl\Web\Widget\NameValueTable;
use Icinga\Module\Bem\BemIssue;

class IssueDetails extends NameValueTable
{
    use TranslationHelper;

    protected $issue;

    protected $host;

    protected $service;

    public function __construct(BemIssue $issue)
    {
        $this->issue = $issue;
        $this->host = $issue->get('host_name');
        $this->service = $issue->get('object_name');
    }

    protected function assemble()
    {
        $i = $this->issue;
        $this->addNameValueRow($this->translate('Host'), $this->host);

        if ($this->service !== null) {
            $this->addNameValueRow($this->translate('Service'), $this->service);
        }
        $this->addNameValuePairs($i->getSlotSetValues());
    }
}
