# Schoolbox Containers

This set of configuration files, as well as
[docker-compose.yaml](../docker-compose.yaml), define how Schoolbox may run
within a self-contained development environment.

## Manual setup

- add this line to `/etc/hosts` or its Windows equivalent:
  ```
  127.0.0.1 docker.dev.schoolbox.cloud
  ```
- `vagrant provision` as per normal: we'll need the database
- extract the files from the provisioned dataset to the mount folder:
  ```shell
  # with data_filename from vagrant.yaml:
  tar -C data/vagrantx -xf vagrantcache/$data_filename --strip-components=1 data
  ```
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
- from the CLI container, setup database copied from Vagrant and reload
  notification bindings:
  ```shell
  sudo docker exec -it schoolbox-cli /bin/bash
  cd /usr/share/schoolbox
  mariadb -h mariadb -u root < docker/schoolbox/sql/setup.sql
  mariadb -h mariadb -u root -D schoolbox < vagrantcache/schoolbox.sql
  mariadb -h mariadb -u root -D schoolbox < docker/schoolbox/sql/post-setup.sql
  tools/notif/route/refresh-binding-cache.php
  ```
- finagle file permissions for PHP-FPM:
  ```shell
  sudo docker exec -it php-fpm \
    chown -R www-data:www-data /var/lib/schoolbox/vagrantx/{cache,storage,twig,purifier}
  ```
- finagle hostname for taskd, and add it to `/etc/hosts`
  ```shell
  sudo docker exec -it schoolbox-taskd /bin/sh
  getent hosts caddy | cut -f1 -d' ' | sed 's/$/ docker.dev.schoolbox.cloud/' >> /etc/hosts
  ```
- finally, visit https://docker.dev.schoolbox.cloud:8443
