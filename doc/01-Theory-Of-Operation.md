<a id="Theory-Of-Operation"></a>Theory of Operation
===================================================

Introduction
------------

This module has been built to keep track of Icinga issues while sending them
to the **BMC (ProactiveNet) Event Manager©** (BEM). While this could also have
been accomplished with a simple Notification Command, that approach has various
problems:

* Event delivery would not be guaranteed
* Icinga has no chance to get aware of lost notifications
* With Icinga 1.x hanging notification commands would block the core
* In small environments this could be solved via recent re-notifications, but
  in larger ones that could potentially flood the Event Manager

This and the strong desire to keep track of all sent events, shipped parameters
and outcome of the executed **ImpactPoster** command.


Architecture
------------

While being an [Icinga Web 2](https://github.com/Icinga/icingaweb2/README.md)
module, the **BEM** module ships with an `icingacli`-based daemon running in
the background. State is kept in a MySQL database, MariaDB is also fine.

Database keeps track of current issues, a record of all single notifications
and current daemon state. 


Sending Events
--------------

Events are sent via the **ImpactPoster** (`msend`) command. A configurable
maximum amount of parallel processes maxes sure BEM 


IDO Polling
-----------

We want to sent 


The Main Loop
-------------

### Filling the queue

**Interval**: twice a second

Picks due issues from the our DB and attaches them to the queue in case they
are not already scheduled

### Refresh Issues from the IDO database

**Interval**: every 5 seconds

Fetches current IDO issues. For each of them checks whether it is already in
our issue list. In case it is, it schedules next notification where required.
If it is unknown and relevant for our cell, it is also scheduled. Otherwise it
is going to be discarded.

### Execute Notifications for Issues in the Run Queue

**Interval**: 10 times a second

This could of course also be instantaneous, and we could keep firing as long as
there are queued issues. However, this way we have an artificial slowdown and a
guarantee that there will be not more than `10 * max_parallel_runners` a second
are going to be sent.

### Update Main Runner statistics

**Interval**: once a second

Information is updated instantaneously, but only written to DB once a second. A
write request only takes place in case any of the collected numbers have been
changed since we last wrote to DB.

### Forced Statistics update

**Interval**: once a minute

To have some kind of heartbeat mechanism, we force statistics to be written to
DB at least once a minute, regardless of whether counters changed or not.

**TODO**: Since we implemented our standby-Cluster logic, this information is no
longer true and needs to be updated.

### Fail-Over node health check

**Interval**: every 3 seconds

In case we're configured as a standby node, this checks the other nodes health
and schedules fail-over/fail-back as required.

### Self-Reset after failure

**Interval**: every 15 seconds

In case of any kind of failure, the Main Runner drops all queues, disconnects
all DB connections and puts itself in a `not-ready` state. This job checks for
that state and tries to re-launch the Main Runner. In case this succeeds, it
transitions into `ready` state and continues to work normally.

### Check for Config changes

**Interval**: every 10 seconds

When loading it's configuration, the Main Runner remembers it's checksum. When
running this job it calculates the current checksum, compares it to the former
one and resets itself in case the checksum changed.


Fail-Over Clustering
--------------------

When running in a clustered environment you probably also want to cluster your
Notification component. Load is not going too be an issue at all, so to keep
things simple this is going to be a simple fail-over/fail-back cluster.

Simply said, when the master is either not reachable or stops working, after a
configurable delay (default: 30 seconds) the standby node starts sending
notifications on it's own. When the master comes back, it immediately stops
doing so.

Feedback Wanted
---------------

Some of the challenges faced when building this had to do with specialities or
specific behavior not mentioned in the **BMC (ProactiveNet) Event Manager©**
documentation. As it is a closed-source product, we sometimes had to figure
things out via trial-and-error.

So, in case you are an experienced BEM user with suggestions that could help to
improving this module, please do not hesitate to contact us!
