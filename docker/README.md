# Schoolbox Containers

This set of configuration files, as well as
[docker-compose.yaml](../docker-compose.yaml), define how Schoolbox may run
within a self-contained development environment.

## Migration from Vagrant

`vagrant.yaml` can be copied to `docker.ini` in Schoolbox's root folder,
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
