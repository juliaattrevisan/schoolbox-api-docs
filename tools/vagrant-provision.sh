#!/bin/bash
# Provisions the vagrant environment
# * Executed after puppet provisioner has set up host
# * Installs the vagrantx instance into this host, then invokes puppet again to configure the host for this instance

# Global constants
readonly SITE_ROOT=/usr/share/schoolbox # Cannot derive this, because it is copied by vagrant to /tmp in guest
readonly LOG=/usr/bin/alaress-logger
readonly L_INFO=200;
readonly L_WARN=300;
readonly L_ERROR=400;
readonly cachedir="$SITE_ROOT"/vagrantcache;

# Global vars
# Defaults
external_db="synergetic";
external_db_host="172.31.15.10";
external_db_db="Synergetic_AUVIC_ALARESS_PRD";
enable_xdebug="true";
force_upgrade=0
instance_registry_url="https://manage.schoolbox.cloud"
declare phpversion_supported                                # Must be specified via script arg

function log(){
  $LOG $L_INFO "$1"
}
function warn(){
  $LOG $L_WARN "$1"
}
function error(){
  $LOG $L_ERROR "$1"
}

if [[ $# -ne 12 ]]; then
  error "Script requires 12 params, found $#"
  exit 1
fi

# Get options
if [ ! -z $1 ]; then data_source=$1; else echo "Please provide data_source"; exit 1; fi;
if [ ! -z $2 ]; then
    data_filename=$2;
else
    echo "Please provide data_filename";
    exit 1;
fi;
if [ ! -z $3 ]; then external_db=$3; fi;
if [ ! -z $4 ]; then external_db_host=$4; fi;
if [ ! -z $5 ]; then external_db_db=$5; fi;
if [ ! -z $6 ]; then enable_xdebug=$6; fi;
if [ ! -z $7 ]; then instance_id_env_var=$7; fi;
if [ -n "$8" ] && [[ "$8" != '0' ]] && [[ "$8" != 'false' ]]; then force_upgrade=1; fi;
if [ -n "$9" ]; then instance_registry_url="$9"; fi;
extra_hostname=${10}
phpversion_supported="${11}"
if [[ -z $phpversion_supported ]]; then
  echo "You must declare desired PHP major version via script arg" >&2
  exit 1
fi
taskd_build_from_local="${12}";

echo "[SB][`date +%Y-%m-%d\ %X`] Setting up vagrant with options:";
echo "[SB][`date +%Y-%m-%d\ %X`] Data Source: $data_source";
echo "[SB][`date +%Y-%m-%d\ %X`] Data Filename: $data_filename";
echo "[SB][`date +%Y-%m-%d\ %X`] External DB: $external_db";
echo "[SB][`date +%Y-%m-%d\ %X`] External DB Host: $external_db_host";
echo "[SB][`date +%Y-%m-%d\ %X`] External DB Name: $external_db_db";

phpversion_current=`php -v | head -n1 | awk '{print $2}' | cut -c1-3`;
echo "[SB][`date +%Y-%m-%d\ %X`] Checking for correct PHP version - current: $phpversion_current, supported: $phpversion_supported";
#if [ "$phpversion_current" != "7.4" ];
#then
#    /usr/bin/update-alternatives --set php /usr/bin/php7.4;
#fi

# Schoolbox <= 20.1 requires PHP 7.1: >= 20.2 requires PHP 7.4
if [ "$phpversion_current" != "$phpversion_supported" ]; then
  error " Current and supported PHP versions do not match. Check 'ubuntu-1804-php74.vagrant.yaml' in puppet repo";
  exit 1
fi

echo "[SB][`date +%Y-%m-%d\ %X`] Retrieving latest Data files";
echo "[SB][`date +%Y-%m-%d\ %X`] Cleaning up zero byte files";
find $cachedir -type f -empty -delete;

# Function to fetch value from .env file by key
# Usage: value=$(read_var $key)
read_var() {
    VAR=$(grep -E "^\s*$1" "$SITE_ROOT"/.env | xargs)
    IFS="=" read -ra VAR <<< "$VAR"
    echo "${VAR[1]}"
}

s3Referer=$(read_var AWS_S3_REFERER)
if [[ -z $s3Referer ]]; then
    echo "Unable to derive S3 Referer secret from .env environment file."
    exit 1;
fi

# Download some files from S3
if ! schoolbox-download-s3-file.sh --data-source "$data_source"  --s3-referer "$s3Referer" --file-name "vagrant-secrets.sql" --cache-dir "$cachedir"; then
  echo "Error: Unable to download s3 file: 'vagrant-secrets.sql'";
  exit 1;
fi
if ! schoolbox-download-s3-file.sh --data-source "$data_source" --s3-referer "$s3Referer" --file-name "vagrant-cleanup-secrets.sql" --cache-dir "$cachedir"; then
  echo "Error: Unable to download s3 file: 'vagrant-cleanup-secrets.sql'";
  exit 1;
fi

echo "[SB][`date +%Y-%m-%d\ %X`] Putting system in maintenance mode";
"$SITE_ROOT"/tools/maintenance/site_down.sh;

echo "[SB][`date +%Y-%m-%d\ %X`] Updating timezone data";
mysql_tzinfo_to_sql /usr/share/zoneinfo 2>/dev/null | mysql --defaults-extra-file=/root/.my.cnf mysql 2>/dev/null;

echo "[SB][`date +%Y-%m-%d\ %X`] Creating Schoolbox MySQL schema";
mysql --defaults-extra-file=/root/.my.cnf -e "CREATE DATABASE schoolbox CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_general_ci';";

echo "[SB][`date +%Y-%m-%d\ %X`] Creating Schoolbox MySQL user";
mysql --defaults-extra-file=/root/.my.cnf schoolbox -e "CREATE USER 'schoolboxvagrant'@'localhost' IDENTIFIED BY 'abc123'";
mysql --defaults-extra-file=/root/.my.cnf schoolbox -e "GRANT ALL ON schoolbox.* to 'schoolboxvagrant'@'localhost'";

echo "[SB][`date +%Y-%m-%d\ %X`] Installing vendor submodules with composer";
cd $cachedir || exit;
vendorfile='';
composerlocksha=`sha1sum ../composer.lock 2>/dev/null | awk '{print $1}'`;
if [ -f "$SITE_ROOT"/vendor/composer.lock.checksum ];
then
    currentvendorsha=`awk '{print $1}' "$SITE_ROOT"/vendor/composer.lock.checksum`;
    if [ "$currentvendorsha" == "$composerlocksha" ];
    then
        echo "[SB][`date +%Y-%m-%d\ %X`] Vendor build up to date, nothing to change";
        vendorfile='ignore';
    fi
fi

if [ "$vendorfile" != 'ignore' ];
then
    check=`grep $composerlocksha vendor.cache 2>/dev/null | wc -l`;
    if [ $check -gt 0 ];
    then
        # Woo - we've located a matching cache file
        vendorfile=`grep $composerlocksha vendor.cache 2>/dev/null | head -n1 | cut -d' ' -f2-100`;
    else
        # No cache files match - boo hoo - check to see if there are new versions
        for branch in develop master;
        do
            # Check if we've already found a matching file
            if [ "$vendorfile" == '' ];
            then
                etag=$(curl -I -H "Referer: $s3Referer" https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-vendor-php-dev.tar.gz 2>/dev/null | grep ETag | cut -d'"' -f2);
                if [ $? -eq 0 ];
                then
                    checketag=`grep $etag vendor.etag.cache 2>/dev/null | wc -l`;
                    if [ $checketag -eq 0 ];
                    then
                        # New/updated version available - download it
                        echo "[SB][`date +%Y-%m-%d\ %X`] Downloading latest vendor build: $branch-vendor-php-dev.tar.gz";
                        wget --no-verbose --header="Referer: $s3Referer" https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-vendor-php-dev.tar.gz -O $branch-vendor-php-dev.tar.gz 2>&1;
                        if [ $? -eq 0 ];
                        then
                            # This is a new file, so remove old references
                            sed -i "/$branch-vendor-php-dev.tar.gz/d" vendor.etag.cache 2>/dev/null;
                            sed -i "/$branch-vendor-php-dev.tar.gz/d" vendor.cache 2>/dev/null;
                            # Add new file to etag cache
                            echo "$etag $branch-vendor-php-dev.tar.gz" >> vendor.etag.cache;
                            # Cleanup for purity
                            rm -fR "$SITE_ROOT"/vagrantcache/vendor 2>/dev/null;
                            # Extract checksum
                            tar xzf $branch-vendor-php-dev.tar.gz vendor/composer.lock.checksum;
                            vendorchecksum=`awk '{print $1}' vendor/composer.lock.checksum`;
                            rm -fR "$SITE_ROOT"/vagrantcache/vendor 2>/dev/null;
                            # Add new file to vendor cache
                            echo "$vendorchecksum $branch-vendor-php-dev.tar.gz" >> vendor.cache;
                            # Check if this is file we need
                            if [ "$vendorchecksum" == "$composerlocksha" ];
                            then
                                vendorfile="$branch-vendor-php-dev.tar.gz";
                            fi
                        fi
                    fi
                fi
            fi
        done
    fi
    cd "$SITE_ROOT" || exit;
    if [ "$vendorfile" != '' ];
    then
        if [ -f "$SITE_ROOT"/vagrantcache/$vendorfile ];
        then
            # Extract content
            echo "[SB][`date +%Y-%m-%d\ %X`] Found matching vendor cache file '$vendorfile', now extracting";
            rm -fR "$SITE_ROOT"/vendor 2>/dev/null;
            tar xzf "$SITE_ROOT"/vagrantcache/$vendorfile;
        else
            # Still no luck, build from scratch with composer
            echo "[SB][`date +%Y-%m-%d\ %X`] No matching vendor cache found. Building with composer";
            su -s /bin/bash -c "cd $SITE_ROOT && tools/composer.sh" vagrant 2>&1
        fi
    else
        # Still no luck, build from scratch with composer
        echo "[SB][`date +%Y-%m-%d\ %X`] No matching vendor cache found. Building with composer";
        su -s /bin/bash -c "cd $SITE_ROOT && tools/composer.sh" vagrant 2>&1
    fi
fi
cd "$SITE_ROOT" || exit;

echo "[SB][`date +%Y-%m-%d\ %X`] Updating composer autoload";
su -s /bin/bash -c "cd $SITE_ROOT && composer dump-autoload -o --no-interaction" vagrant

echo "[SB][`date +%Y-%m-%d\ %X`] Installing instance via 'schoolbox-data-refresh.sh'";

if [[ $force_upgrade == 1 ]]; then
  export SB_FORCE_UPGRADE=1 # Allow unsafe upgrade from really old datasets
fi
if ! INSTANCE_ID=vagrantx schoolbox-data-refresh.sh \
--data-source "$data_source" \
--dataset "$data_filename" \
--s3-referer "$s3Referer" \
--skip-bucket-copy \
--sql-host 'localhost' \
--sql-schema 'schoolbox' \
--sql-user 'schoolboxvagrant' \
--sql-pass 'abc123' \
--cache-dir "$SITE_ROOT/vagrantcache" \
--config amqp_host "'localhost'" \
--config amqp_user "'guest'" \
--config amqp_pass "'guest'" \
--config amqp_vhost "'/'" \
--config external_type "'$external_db'" \
--config external_db_host "'$external_db_host'" \
--config external_db_db "'$external_db_db'" \
--config instance_registry_url "'$instance_registry_url'" \
--config redis_url "'tcp://127.0.0.1:6379'" \
--config redis_ephemeral_url "'tcp://127.0.0.1:6380'" \
--config site_domain "'dev.schoolbox.cloud'" \
--config site_hostname "'vagrant'" \
--config site_school_code "'vagrant'" \
--config site_environment "'client-hosted'" \
--config site_type "'dev'" \
--config title "'Schoolbox [vagrant]'"; then
  error "Unable to install instance. Exiting."
  exit 1
fi

echo "[SB][`date +%Y-%m-%d\ %X`] Applying vagrant SQL fixes";
mysql --defaults-extra-file=/root/.my.cnf schoolbox < "$SITE_ROOT"/sql/vagrant.sql
mysql --defaults-extra-file=/root/.my.cnf schoolbox < $cachedir/vagrant-secrets.sql

# TODO remove this once route+module cache is built in schoolbox-data-refresh.sh
echo "[SB][`date +%Y-%m-%d\ %X`] Loading route+module cache (should be in schoolbox-data-refresh.sh)";
"$SITE_ROOT"/tools/module/refresh-cache.php 2>&1;

# Set ENV VARs for root + vagrant users from vagrant.yaml params
declare -A env_vars=(
  [INSTANCE_ID]="$instance_id_env_var"
  [FACTER_primary_php_majver]="$phpversion_supported"
  [FACTER_vagrant_extra_hostname]="$extra_hostname"
  [FACTER_taskd_build_from_local]="$taskd_build_from_local"
)
for config_name in "${!env_vars[@]}"; do
  config_value=${env_vars[$config_name]}
  for bashrcFile in /home/vagrant/.bashrc /root/.bashrc; do
    # Remove the config if it exists, then insert it
    sed -i -E "/^export\s+$config_name\s*=.+/d" ${bashrcFile}
    echo "export $config_name=\"$config_value\"" >> ${bashrcFile};
  done
  # Need to preserve these ENV vars for vagrant's sudo session
  if ! grep -qE "^Defaults\s+env_keep\s*\+=\s*\".*$config_name" /etc/sudoers.d/vagrant; then
    chmod a+w /etc/sudoers.d/vagrant;
    echo "Defaults env_keep += \"$config_name\"" >> /etc/sudoers.d/vagrant;
    chmod a-w /etc/sudoers.d/vagrant;
  fi
done

# Set up the host for the newly-installed instance
echo "[SB][`date +%Y-%m-%d\ %X`] Invoking puppet to configure host for newly-installed instance";
FACTER_vagrant_extra_hostname="$extra_hostname" FACTER_primary_php_majver="$phpversion_supported" Facter_taskd_build_from_local="$taskd_build_from_local" "$SITE_ROOT"/puppet-apply.sh


echo "[SB][`date +%Y-%m-%d\ %X`] Applying vagrant SQL fixes (post version upgrade)";
cat "$SITE_ROOT"/sql/vagrant.sql | mysql --defaults-extra-file=/root/.my.cnf schoolbox
cat $cachedir/vagrant-secrets.sql | mysql --defaults-extra-file=/root/.my.cnf schoolbox

echo "[SB][`date +%Y-%m-%d\ %X`] Building NPM";
cd $cachedir;
npmfile='';
npmlocksha=`sha1sum "$SITE_ROOT"/npm-shrinkwrap.json 2>/dev/null | awk '{print $1}'`;
if [ -f "$SITE_ROOT"/node_modules/npm.shrinkwrap.checksum ];
then
    currentnpmsha=`awk '{print $1}' "$SITE_ROOT"/node_modules/npm.shrinkwrap.checksum`;
    if [ "$currentnpmsha" == "$npmlocksha" ];
    then
        echo "[SB][`date +%Y-%m-%d\ %X`] NPM build up to date, nothing to change ($npmlocksha)";
        npmfile='ignore';
    fi
fi

if [ "$npmfile" != 'ignore' ];
then
    echo "[SB][`date +%Y-%m-%d\ %X`] NPM build not correct or missing ($npmlocksha)";
    check=`grep $npmlocksha npm.cache 2>/dev/null | wc -l`;
    if [ $check -gt 0 ];
    then
        # Woo - we've located a matching cache file
        npmfile=`grep $npmlocksha npm.cache 2>/dev/null | head -n1 | cut -d' ' -f2-100`;
    else
        # No cache files match - boo hoo - check to see if there are new versions
        for branch in develop master;
        do
            # Check if we've already found a matching file
            if [ "$npmfile" == '' ];
            then
                etag=$(curl -I -H "Referer: $s3Referer" https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-npm.tar.gz 2>/dev/null | grep ETag | cut -d'"' -f2);
                if [ $? -eq 0 ];
                then
                    checketag=`grep $etag npm.etag.cache 2>/dev/null | wc -l`;
                    if [ $checketag -eq 0 ];
                    then
                        # New/updated version available - download it
                        echo "[SB][`date +%Y-%m-%d\ %X`] Downloading latest NPM build: $branch-npm.tar.gz";
                        wget --no-verbose --header="Referer: $s3Referer" https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-npm.tar.gz -O $branch-npm.tar.gz 2>&1;
                        if [ $? -eq 0 ];
                        then
                            # This is a new file, so remove old references
                            sed -i "/$branch-npm.tar.gz/d" npm.etag.cache 2>/dev/null;
                            sed -i "/$branch-npm.tar.gz/d" npm.cache 2>/dev/null;
                            # Add new file to etag cache
                            echo "$etag $branch-npm.tar.gz" >> npm.etag.cache;
                            # Cleanup for purity
                            rm -fR "$SITE_ROOT"/vagrantcache/node_modules 2>/dev/null;
                            # Extract checksum
                            tar xzf $branch-npm.tar.gz node_modules/npm.shrinkwrap.checksum;
                            npmchecksum=`awk '{print $1}' node_modules/npm.shrinkwrap.checksum`;
                            rm -fR "$SITE_ROOT"/vagrantcache/node_modules 2>/dev/null;
                            # Add new file to NPM cache
                            echo "$npmchecksum $branch-npm.tar.gz" >> npm.cache;
                            # Check if this is file we need
                            if [ "$npmchecksum" == "$npmlocksha" ];
                            then
                                npmfile="$branch-npm.tar.gz";
                            fi
                        fi
                    fi
                fi
            fi
        done
    fi
    cd "$SITE_ROOT" || exit 1;
    if [ "$npmfile" != '' ];
    then
        if [ -f "$SITE_ROOT"/vagrantcache/$npmfile ];
        then
            # Extract content
            echo "[SB][`date +%Y-%m-%d\ %X`] Found matching NPM cache file ($npmlocksha) '$npmfile', now extracting";
            rm -fR "$SITE_ROOT"/node_modules 2>/dev/null;
            tar xzf "$SITE_ROOT"/vagrantcache/$npmfile;
        else
            # Still no luck, build from scratch with npm
            echo "[SB][`date +%Y-%m-%d\ %X`] Cache content:";
            cat "$SITE_ROOT"/vagrantcache/npm.cache;
            echo "[SB][`date +%Y-%m-%d\ %X`] No matching NPM cache found. Building with npm";
            "$SITE_ROOT"/tools/pkg-build-npm.sh 2>&1;
        fi
    else
        # Still no luck, build from scratch with npm
        echo "[SB][`date +%Y-%m-%d\ %X`] Cache content:";
        cat "$SITE_ROOT"/vagrantcache/npm.cache;
        echo "[SB][`date +%Y-%m-%d\ %X`] No matching NPM cache found. Building with npm";
        "$SITE_ROOT"/tools/pkg-build-npm.sh 2>&1;
    fi
fi
cd "$SITE_ROOT" || exit 1;

echo "[SB][`date +%Y-%m-%d\ %X`] Building JS/CSS files";
"$SITE_ROOT"/tools/pkg-build-css-js.sh

echo "[SB][`date +%Y-%m-%d\ %X`] Loading all demo data complete";

ffmpegver=`dpkg -l schoolbox-ffmpeg | tail -n1 | awk {'print $3'}`;
echo "[SB][`date +%Y-%m-%d\ %X`] schoolbox-ffmpeg installed: $ffmpegver";

if [ "$enable_xdebug" == "true" ];
then
    echo "[SB][`date +%Y-%m-%d\ %X`] Enabling xdebug";
    if [ -f "$SITE_ROOT"/tools/xdebug.ini ]; then
        echo "[SB][`date +%Y-%m-%d\ %X`] Using custom xdebug: tools/xdebug.ini";
        cp -f "$SITE_ROOT"/tools/xdebug.ini /etc/php/"$phpversion_supported"/mods-available/xdebug.ini;
    else
        echo "[SB][`date +%Y-%m-%d\ %X`] Using default xdebug: tools/xdebug.default.ini";
        cp -f "$SITE_ROOT"/tools/xdebug.default.ini /etc/php/"$phpversion_supported"/mods-available/xdebug.ini;
    fi

    ln -sf /etc/php/"$phpversion_supported"/mods-available/xdebug.ini   /etc/php/"$phpversion_supported"/fpm/conf.d/20-xdebug.ini
    ln -sf /etc/php/"$phpversion_supported"/mods-available/tideways.ini /etc/php/"$phpversion_supported"/fpm/conf.d/20-tideways.ini
    ln -sf /etc/php/"$phpversion_supported"/mods-available/xdebug.ini   /etc/php/"$phpversion_supported"/cli/conf.d/20-xdebug.ini
    ln -sf /etc/php/"$phpversion_supported"/mods-available/tideways.ini /etc/php/"$phpversion_supported"/cli/conf.d/20-tideways.ini


    # If not yet configured, add XDEBUG_CONFIG and PHP_IDE_CONFIG environment vars for 'vagrant' and 'root' users
    for bashrcFile in /home/vagrant/.bashrc /root/.bashrc; do
        if [[ -z `grep -E '^export\s+XDEBUG_CONFIG\s*=\s*"idekey=[^"]+"' ${bashrcFile}` ]];
        then
            echo "export XDEBUG_CONFIG=\"idekey=PHPSTORM\"" >> ${bashrcFile};
        fi

        if [[ -z `grep -E '^export\s+PHP_IDE_CONFIG\s*=\s*"serverName=[^"]+"' ${bashrcFile}` ]];
        then
            # Requires PHPStorm to have a server configured with Name: "10.0.2.15" (this is the default)
            # See "Settings > Languages & Frameworks > PHP > Servers"
            # Technically this is redundant for 'vagrant' sessions, but it required for vagrant sudo and root session
            echo "export PHP_IDE_CONFIG=\"serverName=10.0.2.15\"" >> ${bashrcFile};
        fi
    done

    # Need to preserve these ENV vars for vagrant's sudo session
    for envVar in XDEBUG_CONFIG PHP_IDE_CONFIG; do
        if [[ -z `grep -E "^Defaults\s+env_keep\s*\+=\s*\".*$envVar" /etc/sudoers.d/vagrant` ]];
        then
            chmod a+w /etc/sudoers.d/vagrant;
            echo "Defaults env_keep += \"$envVar\"" >> /etc/sudoers.d/vagrant;
            chmod a-w /etc/sudoers.d/vagrant;
        fi
    done

else
    echo "[SB][`date +%Y-%m-%d\ %X`] Disabling xdebug";
    rm -f /etc/php/"$phpversion_supported"/cli/conf.d/20-xdebug.ini;
    rm -f /etc/php/"$phpversion_supported"/cli/conf.d/20-tideways.ini;
    rm -f /etc/php/"$phpversion_supported"/fpm/conf.d/20-xdebug.ini;
    rm -f /etc/php/"$phpversion_supported"/fpm/conf.d/20-tideways.ini;
fi

echo "[SB][`date +%Y-%m-%d\ %X`] Ensure all services are running";
service schoolbox-epl restart;
eval "service php${phpversion_supported}-fpm restart"
service nginx restart;

echo "[SB][`date +%Y-%m-%d\ %X`] Removing system maintenance mode";
"$SITE_ROOT"/tools/maintenance/site_up.sh;

echo "[SB][`date +%Y-%m-%d\ %X`] Stopping Gotenberg service";
gotenberg_container_ids="$(docker ps -f ancestor=thecodingmachine/gotenberg:6 -q)"
if [[ -n $gotenberg_container_ids ]]; then
  echo -e "$gotenberg_container_ids" | xargs docker stop
fi

echo "[SB][`date +%Y-%m-%d\ %X`] Installing and starting Gotenberg service";
docker run -d --rm --network host --name schoolbox_vagrant_gotenberg thecodingmachine/gotenberg:6;


# Start and rebuild task daemon
# if `taskd_build_from_local` then build from the mounted taskd dir
if [ $taskd_build_from_local -gt 0 ]; then
  "$SITE_ROOT"/tools/taskd/rebuild.sh
else
  accessToken=$(read_var DOCKERHUB_ACCESS_TOKEN)
  if [[ -z $accessToken ]]; then
    echo "[SB][`date +%Y-%m-%d\ %X`] Cannot start task daemon: not building from local dir, and unable to derive Dockerhub access token from .env environment file";
  else
    # Docker login needs the `pass` package to actually accept a password,
    # otherwise this error gets raised:
    # "Cannot autolaunch D-Bus without X11 $DISPLAY"
    apt install -y pass
    "$SITE_ROOT"/tools/taskd/rebuild.sh --pull-from-dockerhub --dockerhub-access-token=$accessToken
  fi
fi;


# call custom provisioning
if [ -f "$SITE_ROOT"/post-provision.sh ];
then
    echo "[SB][`date +%Y-%m-%d\ %X`] Running custom post provisioning";
    "$SITE_ROOT"/post-provision.sh;
fi

# update all dates to current time
"$SITE_ROOT"/tools/increment-instance-dates.php;
