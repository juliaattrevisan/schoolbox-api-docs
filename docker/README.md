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
  mkdir -p db-data
  ```
- `vagrant provision` as per normal: we'll need the database
- once provisioned, backup the database content:
  ```shell
  vagrant ssh
  sudo /usr/share/schoolbox/tools/backup-database.sh
  # this will create a file somewhere in /var/lib/schoolbox: note that path
  cp $backupLocation vagrantcache/schoolbox.sql
  ```
- from the base folder, start Docker containers:
  ```shell
  sudo docker-compose up -d
  ```
- from the CLI container, setup database and copy from Vagrant:
  ```shell
  sudo docker exec -it schoolbox-cli /bin/bash
  cd /usr/share/schoolbox
  mariadb -h mariadb -u root < docker/schoolbox/sql/setup.sql
  mariadb -h mariadb -u root -D schoolbox < vagrantcache/schoolbox.sql
  mariadb -h mariadb -u root -D schoolbox < docker/schoolbox/sql/post-setup.sql
  ```
- finagle file permissions for PHP-FPM:
  ```shell
  sudo docker exec -it php-fpm \
    chown -R www-data:www-data /var/lib/schoolbox/{cache,storage,twig,purifier}
  ```
- finally, visit https://docker.dev.schoolbox.cloud:8443
