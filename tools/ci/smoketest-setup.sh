#!/bin/bash

## Initial directory is where all the resources that are linked are included so save it for later use.
initialdir=$(pwd);                      # eg "/tmp/build/6963e648"
filepath=$(readlink -f "$0");           # eg "/tmp/build/6963e648/schoolbox-pr/tools/ci/smoketest-setup.sh"
fullpath=${filepath%/*}                 # eg "/tmp/build/6963e648/schoolbox-pr/tools/ci"
repo_dir=$(realpath "$fullpath"/../..)  # eg "/tmp/build/6963e648/schoolbox-pr"

readonly instance_id="smoketest"
readonly instance_data_dir=/var/lib/schoolbox/$instance_id

## Open up the datasets that we pulled in.
tar xzf sales-dataset/sales.tar.gz

# TODO. This is currently clobbering this tarball which is being fetched from S3, we can delete the whole S3 workflow
cp -vf "$repo_dir"/test/codeception/Support/Data/SmokeTests/ci_schoolbox_config.tar.gz qa-container-config/qa-container-config.tar.gz
tar xzf qa-container-config/qa-container-config.tar.gz

## As I am using the vagrant image we need to set things up in root directory.
cd /
PHP_VERSION=$1
CONFIG_DIR="/etc/php/${PHP_VERSION}/fpm"
PHP_SERVICE="php${PHP_VERSION}-fpm"

## Start database to initialise with a username and password
echo "Setting up MariaDB"
echo "... starting MariaDB"
service mariadb start

## Initialise database
echo "... setting up users and privileges"
mariadb -e "CREATE DATABASE schoolbox;"
mariadb -e "CREATE USER 'schoolboxvagrant'@'%' IDENTIFIED BY 'abc123';"
mariadb -e "GRANT ALL PRIVILEGES ON schoolbox.* TO 'schoolboxvagrant'@'%';"

# initialise the data itself
echo "... setting up tzdata"
mariadb-tzinfo-to-sql /usr/share/zoneinfo | mariadb -u root mysql -pPassw0rd!

echo "... setting up instance data"
cat "$initialdir"/data/schoolbox.sql | sed -E "s/^\/\*\!50013 DEFINER=.+\*\//\/\* Fallback to default DEFINER \*\//g" | mariadb schoolbox

echo "... updating instance data"
mariadb schoolbox -e "UPDATE config SET value='1',editable='true' WHERE name IN ('resource_booking_enabled','pastoral_enabled','ups_groups_enabled','eotc_enabled','fixtures_enabled','qr_login_enabled','mail_enable');"
mariadb schoolbox -e "UPDATE config SET value='0' WHERE name IN ('tours_enabled');"
echo "MariaDB setup complete"


## Make directories that will be used by our schoolbox instance
mkdir -p /usr/share/schoolbox
mkdir -p /mnt/data/php_tmp
mkdir -p /var/log/schoolbox/"$instance_id"
mkdir -p $instance_data_dir
chmod 777 /mnt/data/php_tmp

## Move our specific config items into the correct folders
cp -rf "$repo_dir"/* /usr/share/schoolbox
cp -f "$initialdir"/configfiles/dhparams.pem /etc/dhparams.pem
cp -rf "$initialdir"/configfiles/nginx/* /etc/nginx
cp -f "$initialdir"/configfiles/update-sql-no-prereq-test.sh /usr/share/schoolbox/tools/update-sql-nocheck.sh
cp -rf "$initialdir"/configfiles/php/${PHP_VERSION}/fpm/* $CONFIG_DIR
cp -f "$initialdir"/configfiles/redis/redis.conf /etc/redis/redis.conf
cp -rf "$initialdir"/data/* $instance_data_dir
cp -rf "$initialdir"/configfiles/bin/* /usr/local/bin

## Go to repository to initialise npm and composer
cd /usr/share/schoolbox

echo "Deploying schoolbox config files"
mkdir -p /etc/schoolbox
cp tools/ci/etc/config.ini /etc/schoolbox/config.ini
cp tools/ci/etc/instances.ini /etc/schoolbox/instances.ini
export INSTANCE_ID="$instance_id"

## Extract the NPM build
tar xzf "$initialdir"/assets-artifact/*-assets.tar.gz;

## Extract the develop vendor build
tar xzf "$initialdir"/vendor-php-artifact-dev/*-vendor-php-dev.tar.gz;

## Make sure the data directory has no permissions issues.
chmod 777 -R $instance_data_dir

## Push out a dns record to the hosts file to ensure all the linkage works properly
echo '127.0.0.1 vagrant.dev.schoolbox.cloud' >> /etc/hosts

## Temp workaround for nginx not being able to access www-data
## (should be removed once caddy replaces nginx here)
usermod -a -G www-data nginx

## Start the services!
service $PHP_SERVICE start
echo 'started php'
service nginx start
echo 'started nginx'
service redis-server start
echo 'started redis-server'
service mariadb start
echo 'started mariadb'

# Set sandbox's site_hostname and site_domain properly:
# in default data, it doesn't match vagrant.dev.schoolbox.cloud
mariadb schoolbox -e "UPDATE config SET value='vagrant' WHERE name='site_hostname';"
mariadb schoolbox -e "UPDATE config SET value='dev.schoolbox.cloud' WHERE name='site_domain';"

### enable stack traces for debugging purposes, as this is a smoketest environment and we want to be able to easily debug any issues that arise
mariadb schoolbox -e "UPDATE config SET value='1' WHERE name='debug_show_stack_trace';"

## set the external services correctly
mariadb schoolbox -e "UPDATE config SET value='tcp://127.0.0.1:6379' WHERE name='redis_url';"
mariadb schoolbox -e "UPDATE config SET value='tcp://127.0.0.1:6380' WHERE name='redis_ephemeral_url';"
mariadb schoolbox -e "UPDATE config SET value='tcp://127.0.0.1:6379' WHERE name='taskd_redis_url';"
mariadb schoolbox -e "UPDATE config SET value='http://localhost:5002' WHERE name='presidio_analysis_host';"
mariadb schoolbox -e "UPDATE config SET value='http://localhost:5001' WHERE name='presidio_anonymisation_host';"
mariadb schoolbox -e "UPDATE config SET value='127.0.0.1' WHERE name='mail_smtp_host';"
mariadb schoolbox -e "UPDATE config SET value='http://localhost:3000' WHERE name='gotenberg_host';"
mariadb schoolbox -e "UPDATE config SET value='localhost' WHERE name='amqp_host';"

# Sandbox has no ephemeral Redis instance; use the persistent instance.
# This must happen both before and after updates:
# * before, because the cache refresh must use the persistent URL
# * after, because an update may reset Schoolbox's ephemeral URL
use_redis_persistent_instance_sql="UPDATE config c1 SET c1.value=(SELECT c2.value FROM config c2 WHERE c2.name='redis_url') WHERE c1.name = 'redis_ephemeral_url';"
mariadb schoolbox -e "$use_redis_persistent_instance_sql"

# May need to composer install here:
# develop vendor build won't have Composer changes made in this PR
tools/composer.sh

# Set data dirs in config table + run upgrade + schema repair scripts
cd tools || exit 1
./module/refresh-cache.php
./generate-dir-sql-dynamically.sh $instance_data_dir | mariadb schoolbox
./build-sql-update.sh      # Build list of update scripts.
./maintenance/site_down.sh # Enter maintenance mode before running update scripts.
./update-sql-nocheck.sh    # Run update scripts (note this must be done before set-plan,
                           # because e.g. set-plan might depend on feature flags added by update scripts.
./maintenance/site_up.sh   # Exit maintenance mode after running update scripts.
./set-plan.php --plan=learning-enhanced

./schema/charset.php --perform
echo "enabling summative report exporter"
mariadb schoolbox -e "UPDATE config SET value='1',editable='true' WHERE name = 'summative_report_enabled';"
echo "enabling differentiation profiles"
mariadb schoolbox -e "UPDATE config SET value='1' WHERE name = 'differentiation_profiles_enabled';"
./module/refresh-cache.php
echo "re building project result Cache" # When we upgrade sales to after 23.0.alpha1 we can remove this
php assessment/projectResultCacheReload.php --perform

# Avoid twig file permission issues (i.e. "Unable to write in the cache directory")
./set-data-dir-perms.sh

# Force the persistent instance again
mariadb schoolbox -e "$use_redis_persistent_instance_sql"

# Set default passwords for users
echo "setting default passwords for users"
mariadb schoolbox -e "UPDATE user SET password = '\$2y\$10\$OaQRb8va2opSxke38QutwOHOKaa3KuZdvIXMgO22IFkI.LTZtCzDK' WHERE username like 'alaress'"
mariadb schoolbox -e "UPDATE user SET password = '\$2y\$10\$GJATSdYOiiDPaSR6.4uN4.z6k8ehM20bv2oPJmBnCrKgiOTJ1VST2' WHERE username not like 'alaress'"

## Finish where we started.
cd "$initialdir"
