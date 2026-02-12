# Schoolbox Containers

This set of configuration files, as well as
[docker-compose.yaml](../docker-compose.yaml), define how Schoolbox may run
within a self-contained development environment running on Docker.

## Containers vs. Images vs. Services

You may see these terms used when discussing the Docker environment.

From https://docs.docker.com/reference/compose-file/services/:

> A service is an abstract definition of a computing resource within an
> application which can be scaled or replaced independently from other
> components. Services are backed by a set of containers, run by the platform
> according to replication requirements and placement constraints. As services
> are backed by containers, they are defined by a Docker image and set of
> runtime arguments. All containers within a service are identically created
> with these arguments.

In summary:
- services define the components of the application
- each service may be run on one or more homogenous and interchangeable
  containers
- each container is defined within an image

## Configuration of the environment

The Docker environment is configured with [docker.env](../docker.env) in the
root of the Schoolbox folder.

The settings in this file override the defaults in
[docker.default.env](../docker.default.env).

## Setting up your IDE

The following integrations ought to be set up in your IDE.

### PHPStorm

#### Xdebug

https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/1765507088/PHPStorm#XDebug
should work for both the Docker and Vagrant environments.

#### Database connection

https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/1765507088/PHPStorm#Database-(docker)
details how PHPStorm's database client can be set up to connect to the Docker
environment's database.

#### PHPUnit

https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/1765507088/PHPStorm#PHPUnit
details how PHPUnit may be set up within PHPStorm.

The instructions are slightly different to Vagrant: note where they diverge.
The method for running tests remains the same.

### VSCode

> TODO write instructions for setting up VSCode

### Migration from Vagrant

#### Automatic

The script [tools/vagrant-yaml-translate.php](../tools/vagrant-yaml-translate.php)
can automatically convert `vagrant.yaml` to a format understandable by Docker's
provision process.

This tool must be run in Vagrant after provision:
```shell
vagrant ssh
cd /usr/share/schoolbox
tools/vagrant-yaml-translate.php > docker.env
```

#### Manual

`vagrant.yaml` can be copied to `docker.env` in Schoolbox's root folder,
however its format will need to be changed from YAML:
- replace the `:` between key and value with a `=`
- remove all space between the `=` and the value
- you may wish to wrap the value in `""`, as the INI files are parsed by
  `source`

Some fields have also changed name:
- `aws_bucket` is now `cdn_bucket`
- `aws_bucket_prefix` is now `cdn_bucket_prefix`

Not all Vagrant configuration fields are relevant in Docker: if it's not in
`docker.default.env`, it won't be relevant to Docker.

### Task daemon

The task daemon can be configured to run from either an already built image,
or from an image built from local code, depending on the
`TASKD_BUILD_FROM_LOCAL` environment variable.

However it's run: the config file [docker-taskd.env](../docker-taskd.env) can be
used to configure it, as per https://github.com/alaress/taskd/blob/master/CONFIGURATION.md.

## Scripts

The name of these scripts is intended to mirror the Vagrant commands for setting
up the Vagrant development environment.

- `up`: builds and starts services for development environment
- `down`: stops services
- `provision`: sets up the Schoolbox instance in the environment
  (`up` must be run first)
- `ssh`: create a shell session (in the `schoolbox-cli` service by default),
  for running commands
- `exec`: execute a shell session in the `schoolbox-cli` service directly
- `logs`: view logs for services in the development environment
- `clean`: cleans up the temporary and cache files which the Docker development
  environment may create
- `compose`: a helper script which calls `docker compose` with the project's
  `.env` files: presumes they were already built with `docker/up`
  - this may be used to run other `docker compose` sub-commands

`up`, `down` and `logs` also pass through any arguments to their equivalent
`docker compose` calls. For example:
- `up --build` builds images before starting services:
  useful for when an image definition has changed
- `down -v` removes volumes (e.g. database data) when stopping services:
  useful for when testing a new database version
- `logs rabbitmq -f` shows logs for the RabbitMQ services only, and follows
  log output

See the documentation for the `docker compose` commands for more information:
- [up](https://docs.docker.com/reference/cli/docker/compose/up/)
- [down](https://docs.docker.com/reference/cli/docker/compose/down/)

These scripts should all be run with `sudo`, unless your current user has been
added to the Docker group: see
https://docs.docker.com/engine/install/linux-postinstall/#manage-docker-as-a-non-root-user.

## Common tasks

### Restarting services

Sometimes, a service may need to be restarted in order to recognise changes to
its configuration. For example:
* when a notifier's definition changes, the `schoolbox-scheduled` service needs
  to restart
* when a service's configuration (e.g. PHP config files) changes, the
  `schoolbox-cli` and `schoolbox-frankenphp` services need to restart

```shell
# view a list of services
docker/compose ps

# restart one or more services
docker/compose restart $service1 ...

```

## Troubleshooting

### First, view the logs

`docker/logs` will show the logs for all services within the Docker environment:
remember it's possible to filter logs to a single service by providing the
service's name.

```shell
# view a list of services:
# the SERVICE column contains the service's name
docker/compose ps

# view logs for a single service,
# viewing only new logs as they arrive
docker/logs $service -fn0

```

### Port collisions

When running Schoolbox on a host which is already running other services (e.g.
another web server, or another Docker environment which provides another web
server), the ports that those other services use may conflict with the ports
used by Schoolbox.

The message will be something like this on `docker/up`:
```
Error response from daemon: failed to set up container networking...:
failed to bind host port for 0.0.0.0:8000:172.18.0.6:80/tcp: address already in use
```

Common culprits are:
- another web server on the host
- another Docker environment which provides a web server

In order to fix this:
- determine the conflicting port:
  - from the above error message, the binding is
    `0.0.0.0:8000:172.18.0.6:80/tcp`, which binds from:
    - your host (0.0.0.0) and port (8000), to;
    - the container on the Docker network (172.18.0.6) and its port (80)
    - using the TCP protocol
- determine which service on your host is using the conflicting port:
  - Docker services may be found with:
    ```shell
    docker ps --filter publish=$PORT
    ```
  - non-Docker services may be found with:
    ```shell
    lsof -i :$PORT
    ```
- once the service is found, either:
  - stop the service:
    - Docker:
      ```shell
      docker stop $SERVICE
      ```
    - non-Docker: consider uninstalling the service, or stopping it manually:
      ```shell
      sudo service $SERVICE stop
      ```
  - change the port which Schoolbox uses in `docker.env`:
    see the `*_PUBLISHED_PORT` variables in `docker.default.env`, and change
    the port to one which doesn't conflict

### AWS authentication errors

Occasionally the `docker/up` process will fail to authenticate, due to an old
stale session:
```
Credentials were refreshed, but the refreshed credentials are still expired.
```

Try logging out with `aws sso logout` before running `docker/up` again.

### Rebuilding the environment

If the environment gets into a bad state, and it can't be resolved any other
way, it can be rebuilt like so:
```shell
# -v removes volumes, including database data
docker/down -v

# remove all stopped containers
docker system prune -a

# restart the environment
docker/up --build
```

This may also be necessary when switching between master and develop branches,
if the base image definition in `docker/base-images/php-frankenphp` has diverged
between the two branches.
