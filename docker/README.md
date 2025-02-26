# Schoolbox Containers

This set of configuration files, as well as
[docker-compose.yaml](../docker-compose.yaml), define how Schoolbox may run
within a self-contained development environment.

## Manual setup

- add this line to `/etc/hosts` or its Windows equivalent:
  ```
  127.0.0.1 docker.dev.schoolbox.cloud
  ```
- create the mounted folders if they don't already exist:
  ```shell
  mkdir -p data/vagrantx/db-data
  ```
- from the base folder, start Docker containers:
  ```shell
  sudo docker-compose up -d
  ```
