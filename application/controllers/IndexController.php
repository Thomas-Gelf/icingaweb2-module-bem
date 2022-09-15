<?php

namespace Icinga\Module\Bem\Controllers;

use gipfl\IcingaWeb2\Link;
use Icinga\Module\Bem\Config;
use ipl\Html\Html;
use ipl\Html\Table;

class IndexController extends ControllerBase
{
    public function init()
    {
        $this->prepareTabs();
    }

    public function indexAction()
    {
        $this->setAutorefreshInterval(10);
        $this->addTitle($this->translate('BEM - configured Cells'));

        $cellNames = (new Config())->enumConfiguredCells();

        if (empty($cellNames)) {
            $this->content()->add(
                Html::tag('p', null, Html::sprintf(
                    'No cells have been configured, please read the %s documentation',
                    Link::create(
                        $this->translate('Installation And Configuration'),
                        'doc/module/bem/chapter/Installation-And-Configuration',
                        null,
                        ['data-base-target' => '_next']
                    )
                ))
            );

            return;
        }

        $table = new Table();
        $table->addAttributes([
            'data-base-target' => '_next',
            'class' => 'common-table table-row-selectable'
        ]);
        $table->getHeader()->add(
            $table::row([
                $this->translate('Cell name')
            ], null, 'th')
        );
        foreach ($cellNames as $cellName) {
            $table->getBody()->add(
                $table::row([
                    Link::create($cellName, 'bem/cell', ['name' => $cellName])
                ])
            );
        }

        $this->content()->add($table);
    }
}
