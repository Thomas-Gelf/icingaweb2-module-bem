Icinga Web 2 BEM module
=======================

This Icinga Web 2 module has been written to allow for an easy integration of
your Icinga environment with **BMC (ProactiveNet) Event Manager©** installations.

This component is responsible of sending events to the BMC Event Console through
the **ImpactPoster** (`msend`). This module provides a standalone notification
command or can alternatively run as a daemon in the background.

Main advantage of the daemon variant is, that it allows to send all pending
issues at once with a given timeout. It will then eriodically re-send all
issues to refresh them for the Event Manager.

Read more about how this works in [Theory of Operation](doc/01-Theory-Of-Operation.md).
In case you like what you see, please head on to [Installation and Configuration](
doc/02-Installation-And-Configuration.md).
