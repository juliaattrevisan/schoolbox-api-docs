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
- from the CLI container, setup database:
  ```shell
  docker exec -it schoolbox-cli /bin/bash
  cd /usr/share/schoolbox
  mariadb -h mariadb -u root
  ```
  ```mysql
  DROP DATABASE IF EXISTS schoolbox;
  CREATE DATABASE schoolbox CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';

  # for now need to create vagrant user
  CREATE USER IF NOT EXISTS 'schoolboxvagrant'@'localhost' IDENTIFIED BY 'abc123';

  # create Docker users
  CREATE USER IF NOT EXISTS 'schoolboxdocker'@'php-fpm' IDENTIFIED BY 'abc123';
  CREATE USER IF NOT EXISTS 'schoolboxdocker'@'schoolbox-cli' IDENTIFIED BY 'abc123';

  # grant everything to all these users
  GRANT ALL ON schoolbox.* to 'schoolboxvagrant'@'localhost';
  GRANT ALL ON schoolbox.* to 'schoolboxdocker'@'php-fpm';
  GRANT ALL ON schoolbox.* to 'schoolboxdocker'@'schoolbox-cli';
  ```
- using the backup from before:
- extract `schoolbox.sql` out of a dataset (e.g. `vagrantcache/demo.tar.gz`)
  into the `vagrantcache` folder, and then:
  ```shell
  docker exec -it schoolbox-cli /bin/bash
  cd /usr/share/schoolbox
  mariadb -h mariadb -u root -D schoolbox -p < vagrantcache/schoolbox.sql
  mariadb -h mariadb -u root -D schoolbox -p < sql/vagrant.sql
  ```
- finagle database settings for Docker:
  ```sql
  UPDATE config SET value='tcp://redis-storage:6379' WHERE name='redis_url';
  UPDATE config SET value='tcp://redis-cache:6379' WHERE name='redis_ephemeral_url';
  UPDATE config SET value='tcp://redis-storage:6379' WHERE name='taskd_redis_url';
  ```
- finagle file permissions for PHP-FPM:
  ```shell
  docker exec -it php-fpm \
    chown -R www-data:www-data /var/lib/schoolbox/{cache,storage,twig,purifier}
  ```
- finally, visit https://docker.dev.schoolbox.cloud:8443
