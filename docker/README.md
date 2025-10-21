# Schoolbox Containers

This set of configuration files, as well as
[docker-compose.yaml](../docker-compose.yaml), define how Schoolbox may run
within a self-contained development environment.

## Configuration

The Docker environment is configured with [docker.env](../docker.env) in the
root of the Schoolbox folder.

The settings in this file override the defaults in
[docker.default.env](../docker.default.env).

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
`docker.default.ini`, it won't be relevant to Docker.

### Task daemon

The task daemon can be configured to run from either an already built image,
or from an image built from local code, depending on the
`TASKD_BUILD_FROM_LOCAL` environment variable.

However it's run: the config file [docker-taskd.env](../docker-taskd.env) can be
used to configure it, as per https://github.com/alaress/taskd/blob/master/CONFIGURATION.md.

## Scripts

The name of these scripts is intended to mirror the Vagrant commands for setting
up the Vagrant development environment.

- `up`: builds and starts containers for development environment
- `down`: stops containers
- `provision`: sets up the Schoolbox instance in the started containers
  (`up` must be run first)
- `ssh`: create a shell session in the `schoolbox-cli` container, for running
  commands
- `logs`: view logs for containers in the development environment
- `clean`: cleans up the temporary and cache files which the Docker development
  environment may create
- `compose`: a helper script which calls `docker compose` with the project's
  `.env` files: presumes they were already built with `docker/up`
  - this may be used to run other `docker compose` sub-commands

`up`, `down` and `logs` also pass through any arguments to their equivalent
`docker compose` calls. For example:
- `up --build` builds images before starting containers:
  useful for when an image definition has changed
- `down -v` removes volumes (e.g. database data) when stopping containers:
  useful for when testing a new database version
- `logs rabbitmq` shows logs for the RabbitMQ container only

See the documentation for the `docker compose` commands for more information:
- [up](https://docs.docker.com/reference/cli/docker/compose/up/)
- [down](https://docs.docker.com/reference/cli/docker/compose/down/)

These scripts should all be run with `sudo`, unless your current user has been
added to the Docker group: see
https://docs.docker.com/engine/install/linux-postinstall/#manage-docker-as-a-non-root-user.

## Troubleshooting

Sometimes the Docker environment may fail to build as expected. In that case,
you may wish to clean up the entire environment:

```bash
docker/down
docker system prune --all
```
