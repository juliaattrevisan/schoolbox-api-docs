# Instance Routing

When Schoolbox receives a request, the first thing that Schoolbox must do is
determine which instance will process the request.

It is possible for a single installation to serve multiple Schoolbox instances:
thus, the instance routing process yields settings which are required to connect
to a database which contains a Schoolbox instance's configuration and data;
that is, connection details for that database.

Each served instance's details are loaded from the local file
`/etc/schoolbox/instances.ini` in `www/conf/conf.php`: this file is able to
then determine which app configuration is actually being served, as multiple
Schoolbox instances may be served from the one installation of Schoolbox.

The instance which is being served may be determined by:
* for CLI scripts, the `INSTANCE_ID` environment variable is matched directly
  against a section in `instances.ini`:
    * for example it may be provided by:
    ```shell
    INSTANCE_ID=vagrantx tools/tool.sh
    ```
* for web requests, the `Host` or `X-Forwarded-Host` header of the HTTP request
  is looked up in the `hostnames` values of each section: if it's found, the
  parent section is used as the instance ID

Either way, once the instance is determined the
[Schoolbox database](../io/schoolbox-database.md) connection details
(host/schema/user/password) are then used to connect to the database,
to determine the [instance configuration](./instance-configuration.md) and later
to retrieve data.
