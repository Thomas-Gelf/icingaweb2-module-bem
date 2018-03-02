<?php

/** @var Icinga\Application\Modules\Module $this */
$section = $this->menuSection(N_('BEM Notifications'))
    ->setIcon('bell-alt');
$section->add(N_('Current Issues'))->setUrl('bem/issues');
$section->add(N_('Sent Notifications'))->setUrl('bem/notifications');
$section->add(N_('Configured Cells'))->setUrl('bem');
