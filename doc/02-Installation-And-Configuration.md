<a id="Installation-And-Configuration"></a>Installation and Configuration
=========================================================================

Requirements
------------

* [Icinga Web 2](https://github.com/Icinga/icingaweb2) (&gt;= 2.4.1)
* [ipl](https://github.com/Thomas-Gelf/ipl) (p)rototype for future Icinga PHP Library)
* PHP (5.x &gt;= 5.4 or 7.x)
* MariaDB or MySQL (&gt; 5.5.3)
* ImpactPoster (msend)

The Icinga Web 2 `monitoring` module needs to be configured and enabled.

Database
--------

### Create an empty database on MariaDB (or MySQL)

HINT: You should replace `some-password` with a secure custom password.

    mysql -e "CREATE DATABASE icinga_bem CHARACTER SET 'utf8mb4';
       GRANT ALL ON icinga_bem.* TO icinga_bem@localhost IDENTIFIED BY 'some-password';"

### Create the BEM module schema

    mysql icinga_bem < schema/mysql.sql

### Create a related Icinga Web 2 Database resource

In your web frontend please go to `Configuration / Application / Resources`
and create a new database resource pointing to your newly created database.
Please make sure that you choose `utf8mb4` as an encoding.

Alternatively, you could also manally add a resource definition to your
resources.ini:

#### /etc/icingaweb2/resources.ini

```ini
[icinga_bmc]
type = "db"
db = "mysql"
host = "localhost"
port = "3306"
dbname = "icinga_bmc"
username = "icingaweb_bmc"
password = "***"
charset = "utf8mb4"
```

Cells
-----

This module is able to deal with multiple BEM cells, each one with a distinct
configuration. Every configuration has to be in a dedicated `ini` file in this
modules `cells` configuration subdirectory. Usually, this is
`/etc/icingaweb2/modules/bmc/cells`.

Sample Cell: "integration"
--------------------------

Let's immagine you're running a dedicated BMC ProactiveNet Event Manager installation
for integration testing. That system makes a perfect candidate for our first setup.

### /etc/icingaweb2/modules/bmc/cells/devel.ini
```ini
[main]
cell = "integration"
; Alternatively:
; cell = "host/port#encKey"

object_class = "ICINGA"
db_resource = "icinga_bmc"

; resend_interval = 900
; mcell_home = "/usr/local/msend"

; only on standby node
; standby_check = "other-node!Icinga Cluster Keepalive"

; Params prefixed with mc_ are required
[msend_params]
msg = "{host:getLink}: {output}"
mc_host = "{host_name:stripDomain}"
mc_object = "{service_name|'hoststatus'}"
mc_object_class = "{host.vars.bmc_object_class}"
mc_object_uri = "{host:getLink}"
mc_parameter = "status"
mc_timeout = "{host.vars.bmc_timeout|7200}"
mc_priority = "{host.vars.priority}"
custom_os = "{host.vars.contact_team}"
custom_contact = "{host.vars.contact_team}"
```

Enabling and running the background daemon
------------------------------------------

Once you played around with this modules and everything works fine when running
on commandline, time has come to enable a background daemon sending your Icinga
issues to BEM.

    cp contrib/systemd/icinga-bem@.service  /etc/systemd/system/
    systemctl daemon-reload
    systemctl enable icinga-bem@integration
    systemctl start icinga-bem@integration

That's it, your daemon should now be running. Feel free to configure as many
cells as you want, each of them with a distinct database and systemd service
instance.
