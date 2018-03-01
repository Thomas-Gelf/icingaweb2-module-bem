<?php

/** @var Icinga\Application\Modules\Module $this */
$section = $this->menuSection(N_('Problems'));
$section->add(N_('BEM Notifications'))->setUrl('bem');
