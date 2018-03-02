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
[icinga_bem]
type = "db"
db = "mysql"
host = "localhost"
port = "3306"
dbname = "icinga_bem"
username = "icingaweb_bem"
password = "***"
charset = "utf8mb4"
```

Cells
-----

This module is able to deal with multiple BEM cells, each one with a distinct
configuration. Every configuration has to be in a dedicated `ini` file in this
modules `cells` configuration subdirectory. Usually, this is
`/etc/icingaweb2/modules/bem/cells`.

Sample Cell: "integration"
--------------------------

Let's immagine you're running a dedicated BMC ProactiveNet Event Manager installation
for `integration` testing. That system makes a perfect candidate for our first setup.

### /etc/icingaweb2/modules/bem/maps.ini
```ini
[host_states]
UP = OK
DOWN = CRITICAL
UNREACHABLE = MINOR

[service_states]
OK = OK
WARNING = MINOR
UNKNOWN = MAJOR
CRITICAL = CRITICAL

[downgrade]
CRITICAL = WARNING
MINOR = WARNING
MAJOR = WARNING
```

### /etc/icingaweb2/modules/bem/cells/integration.ini
```ini
[main]
cell = "integration"
; Alternatively:
; cell = "host/port#encKey"

object_class = "ICINGA"
db_resource = "icinga_bem"

[icingaweb]
url = "https://monitoring.example.com/icingaweb2/"

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
; custom_os = "{host.vars.contact_team}"
; custom_contact = "{host.vars.contact_team}"

[whitelist]
filter1 = "host.vars.priority&host.vars.priority<5"

[blacklist]
filter1 = "object_type=service&(host.vars.priority>=3|host.vars.priority<=5)"
filter2 = "host.vars.cmdb_state=end*"
filter3 = "host.vars.monitored_by_icinga1"

; Map service states
[modifier.0]
filter = "object_type=service"
modifier = map
map_name = service_states

; Map host states
[modifier.1]
filter = "object_type=host"
modifier = map
map_name = host_states

; Downgrade unused and maintenance hosts
[modifiers.2]
filter = "host.vars.cmdb_state!=in use|host.vars.maintenance"
modifier = map
map_name = downgrade
```

Let's explain the various settings. Configuration consists of two sections,
`[main]` and `[msend_params]`. First, the generic settings in the `[main]`
section:

| Setting      | Description                                                      | Default          | Required |
|--------------|------------------------------------------------------------------|------------------|----------|
| cell         | Event Manager cell, basically the instance receiving your events | -                | YES      |
| object_class | Used as `-a` when calling `msend`                                | ICINGA           | YES      |
| db_resource  | Resource name referencing the DB connection in `resources.ini`   | -                | YES      |
| web_url      | The base URL of your Icinga Web 2 instance                       | -                | NO       |
| mcell_home   | This is your `MCELL_HOME`, where msend has been installed        | /usr/local/msend | YES      |

Next section is [msend_params]. Basically, every setting here is passed as a
slot value to the **ImpactPoster** command (`msend`) in an escaped way via `-b`.
Please read the Event Manager documentation for a meaning of the different keys.

As it's value you can use any free string, filled with optional variables. A
variable needs to be surrounded with brackets (`{}`), might have alternative
variables or strings (separated with `|`) as a fallback or use modifiers after
a colon (`:`).

#### Available objects

* `host`: the host, available for every issue
* `service`: available only for service issues. Don't worry, accessing service
  properties while handling host problems doesn't fail, it just gives a `null`
  result
* `object`: a reference to either the host or the service, depending on what
  kind of event we are handling

#### Object properties

In the following examples, `<object>` is just a placeholder. Where it's used,
the shown property is available for `host`, `service` and `object`.
* `<object>.name`
* `<object>.vars.<varname>`

#### Modifiers

* `:getLink`: use `host:getLink`, `service:getLink` or `object:getLink`
* `:stripDomain`:

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
