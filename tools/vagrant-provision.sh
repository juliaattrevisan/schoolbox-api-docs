#!/bin/bash
# Ensure running an appropriate era vagrant box
if [ `cat /home/vagrant/.vagrant.buildtimestamp` -gt 1528782104 ];
then
    echo "[SB][`date +%Y-%m-%d\ %X`] Vagrant box meets minimum requirements: 1528782105 Current: "`cat /home/vagrant/.vagrant.buildtimestamp`;
else
    echo "Error: Your vagrant box is out of date. Please ensure you are using the latest version (>= 1528782105). Current: "`cat /home/vagrant/.vagrant.buildtimestamp` >2;
    echo "To fix this:";
    echo "  vagrant destroy;";
    echo "  vagrant box remove ubuntu-1404-php71;";
    echo "  vagrant box remove ubuntu-1804-php71;";
    if [ -f /vagrant/vagrant.yaml ] && [ `grep vagrant_box /vagrant/vagrant.yaml | grep -v '#' | wc -l` -gt 0 ];
    then
        echo "then:";
        echo "  remove the 'vagrant_box' line from your vagrant.yaml file";
    fi
    echo "then:";
    echo "  vagrant up;";
    exit 1;
fi;

cachedir='/vagrant/vagrantcache';
mkdir -p $cachedir;
cd $cachedir;

# Defaults
external_db="synergetic";
external_db_host="172.31.15.10";
external_db_db="Synergetic_AUVIC_ALARESS_PRD";
enable_xdebug="true";

# Get options
if [ ! -z $1 ]; then data_source=$1; else echo "Please provide data_source"; exit 1; fi;
if [ ! -z $2 ];
then
    data_filename=$2;
    data_filename_short=`echo "$data_filename" | cut -d'?' -f1`;
else
    echo "Please provide data_filename";
    exit 1;
fi;
if [ ! -z $3 ]; then external_db=$3; fi;
if [ ! -z $4 ]; then external_db_host=$4; fi;
if [ ! -z $5 ]; then external_db_db=$5; fi;
if [ ! -z $6 ]; then enable_xdebug=$6; fi;

echo "[SB][`date +%Y-%m-%d\ %X`] Setting up vagrant with options:";
echo "[SB][`date +%Y-%m-%d\ %X`] Data Source: $data_source";
echo "[SB][`date +%Y-%m-%d\ %X`] Data Filename: $data_filename";
echo "[SB][`date +%Y-%m-%d\ %X`] External DB: $external_db";
echo "[SB][`date +%Y-%m-%d\ %X`] External DB Host: $external_db_host";
echo "[SB][`date +%Y-%m-%d\ %X`] External DB Name: $external_db_db";

phpversion=`php -v | head -n1 | awk '{print $2}' | cut -c1-3`;
echo "[SB][`date +%Y-%m-%d\ %X`] Checking for correct PHP version - current: $phpversion";
if [ "$phpversion" != "7.1" ];
then
    /usr/bin/update-alternatives --set php /usr/bin/php7.1;
fi

echo "[SB][`date +%Y-%m-%d\ %X`] Retrieving latest Data files";
echo "[SB][`date +%Y-%m-%d\ %X`] Cleaning up zero byte files";
find $cachedir -type f -empty -delete;


# TODO remove this, add Composer to base box
echo "[SB][`date +%Y-%m-%d\ %X`] Installing Composer";
apt-get install -y composer


# function to retrieve latest files from S3, needs $S3file set first
S3file_short='';
getS3file() {
    if [ -z $S3file ];
    then
        echo "Error - missing S3file target";
    else
        predir=`pwd`;
        cd $cachedir;

        curletag=`curl -I -H 'Referer: JIFrHts6WT7CeAU8xvlEFcG6VV3J89BO3CZMrQwF' $data_source/$S3file 2>/dev/null`;
        if [ $? -eq 0 ];
        then
            responsecode=`echo "$curletag" | egrep 'HTTP/(1.0|1.1|2)' | cut -d' ' -f2`;
            if [ "$responsecode" != "200" ];
            then
                echo "[SB][`date +%Y-%m-%d\ %X`] File unable to be retrieved (code $responsecode), using cache: $S3file";
            else
                etag=`echo "$curletag" | grep ETag | cut -d'"' -f2`;
                # If S3file_short is not set, assume same as S3file for simplicity
                if [ -z $S3file_short ] || [ "$S3file_short" == '' ]; then S3file_short="$S3file"; fi;
                # Auto cleanup etag cache file if actual file is missing
                if [ ! -f $S3file_short ]; then rm -f $S3file_short.etag.cache; fi;
                checketag=`grep $etag $S3file_short.etag.cache 2>/dev/null | wc -l`;
                if [ $checketag -eq 0 ];
                then
                    # New/updated version available - download it
                    echo "[SB][`date +%Y-%m-%d\ %X`] Downloading latest $S3file_short";
                    wget --no-verbose --header="Referer: JIFrHts6WT7CeAU8xvlEFcG6VV3J89BO3CZMrQwF" $data_source/$S3file -O $S3file_short 2>&1;
                    if [ $? -eq 0 ];
                    then
                        # This is a new file, so remove old references
                        sed -i "/$S3file_short/d" $S3file_short.etag.cache 2>/dev/null;
                        # Add new file to etag cache
                        echo "$etag $S3file_short" >> $S3file_short.etag.cache;
                    else
                        # when download fails, remove bad download
                        echo "[SB][`date +%Y-%m-%d\ %X`] Download failed of: $S3file_short";
                        rm -vf $S3file_short;
                    fi
                else
                    echo "[SB][`date +%Y-%m-%d\ %X`] File up to date: $S3file_short";
                fi
            fi
        else
            echo "[SB][`date +%Y-%m-%d\ %X`] File unable to be retrieved, using cache: $S3file";
        fi

        cd $predir;
    fi
}

S3file='vagrant.sql'
S3file_short='vagrant.sql'
getS3file;
if [ ! -f $cachedir/$S3file_short ];
then
    echo "Error: file missing $S3file";
    exit 1;
fi

S3file='vagrant-cleanup.sql'
S3file_short='vagrant-cleanup.sql'
getS3file;
if [ ! -f $cachedir/$S3file_short ];
then
    echo "Error: file missing $S3file";
    exit 1;
fi

S3file="$data_filename";
S3file_short="$data_filename_short";
getS3file;
if [ ! -f $cachedir/$S3file_short ];
then
    echo "Error: file missing $S3file";
    exit 1;
fi

S3file="tls-vagrant.dev.schoolbox.cloud.tar.gz";
S3file_short="tls-vagrant.dev.schoolbox.cloud.tar.gz";
getS3file;
if [ ! -f $cachedir/$S3file_short ];
then
    echo "Error: file missing $S3file";
    exit 1;
fi

echo "[SB][`date +%Y-%m-%d\ %X`] Putting system in maintenance mode";
/vagrant/tools/maintenance/site_down.sh;

echo "[SB][`date +%Y-%m-%d\ %X`] Updating timezone data";
mysql_tzinfo_to_sql /usr/share/zoneinfo 2>/dev/null | mysql --defaults-extra-file=/root/.my.cnf mysql 2>/dev/null;

echo "[SB][`date +%Y-%m-%d\ %X`] Removing current Schoolbox MySQL database";
mysql --defaults-extra-file=/root/.my.cnf -e "DROP DATABASE IF EXISTS schoolbox";

echo "[SB][`date +%Y-%m-%d\ %X`] Removing current Schoolbox MySQL user";
mysql --defaults-extra-file=/root/.my.cnf -e "DROP USER IF EXISTS 'schoolboxvagrant'@'localhost'";

echo "[SB][`date +%Y-%m-%d\ %X`] Removing current Schoolbox Data directory";
rm /vagrant/data/* -fR;

echo "[SB][`date +%Y-%m-%d\ %X`] Creating Schoolbox data directories";
cd /vagrant;
tar -xzf $cachedir/$data_filename_short;
rm -rf /vagrant/data/cache;
/vagrant/tools/create-data-dirs.sh /vagrant/data;

echo "[SB][`date +%Y-%m-%d\ %X`] Setting up browscap cache";
mkdir -p /vagrant/tools/browscap;
/vagrant/vendor/browscap/browscap-php/bin/browscap-php browscap:update --cache=/vagrant/tools/browscap/ --remote-file=Lite_PHP_BrowscapINI

echo "[SB][`date +%Y-%m-%d\ %X`] Loading Schoolbox MySQL Demo data";
mysql --defaults-extra-file=/root/.my.cnf -e "CREATE DATABASE schoolbox CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_general_ci';";
mysql --defaults-extra-file=/root/.my.cnf schoolbox < /vagrant/data/schoolbox.sql;
mysql --defaults-extra-file=/root/.my.cnf schoolbox -e "UPDATE config SET value='$external_db' WHERE name='external_type'";
mysql --defaults-extra-file=/root/.my.cnf schoolbox -e "UPDATE config SET value='$external_db_host' WHERE name='external_db_host'";
mysql --defaults-extra-file=/root/.my.cnf schoolbox -e "UPDATE config SET value='$external_db_db' WHERE name='external_db_db'";
mysql --defaults-extra-file=/root/.my.cnf schoolbox -e "UPDATE config SET value='vagrant' WHERE name='site_school_code'";

echo "[SB][`date +%Y-%m-%d\ %X`] Creating Schoolbox MySQL user";
mysql --defaults-extra-file=/root/.my.cnf schoolbox -e "CREATE USER 'schoolboxvagrant'@'localhost' IDENTIFIED BY 'abc123'";
mysql --defaults-extra-file=/root/.my.cnf schoolbox -e "GRANT ALL ON schoolbox.* to 'schoolboxvagrant'@'localhost'";

echo "[SB][`date +%Y-%m-%d\ %X`] Creating Schoolbox conf file";
cp -f /vagrant/www/conf/tarball.conf.php /vagrant/www/conf/conf.php;
sed -i 's/xxxxxxxx/vagrantx/g' /vagrant/www/conf/conf.php;
sed -i -E "s/^define\(['\"]SQL_user['\"].+$/define\(\"SQL_user\", \"schoolboxvagrant\"\);/g" /vagrant/www/conf/conf.php;
sed -i 's/xxxxxx/abc123/g' /vagrant/www/conf/conf.php;

echo "[SB][`date +%Y-%m-%d\ %X`] Installing vendor submodules with composer";
cd $cachedir;
vendorfile='';
composerlocksha=`sha1sum ../composer.lock 2>/dev/null | awk '{print $1}'`;
if [ -f /vagrant/vendor/composer.lock.checksum ];
then
    currentvendorsha=`awk '{print $1}' /vagrant/vendor/composer.lock.checksum`;
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
                etag=`curl -I -H 'Referer: JIFrHts6WT7CeAU8xvlEFcG6VV3J89BO3CZMrQwF' https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-vendor-php-dev.tar.gz 2>/dev/null | grep ETag | cut -d'"' -f2`;
                if [ $? -eq 0 ];
                then
                    checketag=`grep $etag vendor.etag.cache 2>/dev/null | wc -l`;
                    if [ $checketag -eq 0 ];
                    then
                        # New/updated version available - download it
                        echo "[SB][`date +%Y-%m-%d\ %X`] Downloading latest vendor build: $branch-vendor-php-dev.tar.gz";
                        wget --no-verbose --header="Referer: JIFrHts6WT7CeAU8xvlEFcG6VV3J89BO3CZMrQwF" https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-vendor-php-dev.tar.gz -O $branch-vendor-php-dev.tar.gz 2>&1;
                        if [ $? -eq 0 ];
                        then
                            # This is a new file, so remove old references
                            sed -i "/$branch-vendor-php-dev.tar.gz/d" vendor.etag.cache 2>/dev/null;
                            sed -i "/$branch-vendor-php-dev.tar.gz/d" vendor.cache 2>/dev/null;
                            # Add new file to etag cache
                            echo "$etag $branch-vendor-php-dev.tar.gz" >> vendor.etag.cache;
                            # Cleanup for purity
                            rm -fR /vagrant/vagrantcache/vendor 2>/dev/null;
                            # Extract checksum
                            tar xzf $branch-vendor-php-dev.tar.gz vendor/composer.lock.checksum;
                            vendorchecksum=`awk '{print $1}' vendor/composer.lock.checksum`;
                            rm -fR /vagrant/vagrantcache/vendor 2>/dev/null;
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
    cd /vagrant;
    if [ "$vendorfile" != '' ];
    then
        if [ -f /vagrant/vagrantcache/$vendorfile ];
        then
            # Extract content
            echo "[SB][`date +%Y-%m-%d\ %X`] Found matching vendor cache file '$vendorfile', now extracting";
            rm -fR /vagrant/vendor 2>/dev/null;
            tar xzf /vagrant/vagrantcache/$vendorfile;
        else
            # Still no luck, build from scratch with composer
            echo "[SB][`date +%Y-%m-%d\ %X`] No matching vendor cache found. Building with composer";
            /vagrant/tools/composer.sh 2>&1;
        fi
    else
        # Still no luck, build from scratch with composer
        echo "[SB][`date +%Y-%m-%d\ %X`] No matching vendor cache found. Building with composer";
        /vagrant/tools/composer.sh 2>&1;
    fi
fi
cd /vagrant;

echo "[SB][`date +%Y-%m-%d\ %X`] Removing cache directories";
/vagrant/tools/clean-cache-dir.sh;

echo "[SB][`date +%Y-%m-%d\ %X`] Building SQL updates";
cd tools;
./build-sql-update.sh >/dev/null;
cd /vagrant;

echo "[SB][`date +%Y-%m-%d\ %X`] Generating data dir sql fixes";
cp -f $cachedir/vagrant.sql /tmp/vagrant.sql;
/vagrant/tools/generate-dir-sql.sh /vagrant/data /vagrant >> /tmp/vagrant.sql;

echo "[SB][`date +%Y-%m-%d\ %X`] Applying vagrant SQL fixes";
mysql --defaults-extra-file=/root/.my.cnf schoolbox < /tmp/vagrant.sql

echo "[SB][`date +%Y-%m-%d\ %X`] Upgrading demo data with latest SQL updates";
mkdir /var/lib/vagrant -p > /dev/null;
/vagrant/tools/update-sql.sh;
rm -f /vagrant/updates/*/scripts_list > /dev/null;
rm -R /var/lib/vagrant > /dev/null;
rm -f /vagrant/www/conf/pre*conf.php;

echo "[SB][`date +%Y-%m-%d\ %X`] Generating data dir sql fixes (post version upgrade)";
cp -f $cachedir/vagrant.sql /tmp/vagrant.sql;
/vagrant/tools/generate-dir-sql.sh /vagrant/data /vagrant >> /tmp/vagrant.sql;

echo "[SB][`date +%Y-%m-%d\ %X`] Applying vagrant SQL fixes (post version upgrade)";
mysql --defaults-extra-file=/root/.my.cnf schoolbox < /tmp/vagrant.sql

echo "[SB][`date +%Y-%m-%d\ %X`] Enabling Schoolbox nginx vhost";
chown -fR vagrant:vagrant /mnt/data/php_tmp;
sed -i 's/php_flag\[display_startup_errors\]\ \=\ off/php_flag\[display_startup_errors\]\ \=\ on/g' /etc/php/7.1/fpm/pool.d/schoolbox*.conf;
sed -i 's/php_flag\[display_errors\]\ \=\ off/php_flag\[display_errors\]\ \=\ on/g' /etc/php/7.1/fpm/pool.d/schoolbox*.conf;
sed -i 's/php_flag\[html_errors\]\ \=\ off/php_flag\[html_errors\]\ \=\ on/g' /etc/php/7.1/fpm/pool.d/schoolbox*.conf;
cp -f /vagrant/tools/nginx-vhost-schoolbox-websocket.conf /etc/nginx/conf.d/;
cp -f /vagrant/tools/nginx-vhost-schoolbox-websocket-ssl.conf /etc/nginx/conf.d/;
sed -i 's/var\/lib\/schoolbox/vagrant/g' /etc/php/7.1/fpm/pool.d/schoolbox*;
sed -i 's/www-data/vagrant/g' /etc/php/7.1/fpm/pool.d/schoolbox*;
# disable nginx open file cache since not a production server
sed -i '/open_file_cache/d' /etc/nginx/nginx.conf;
cd $cachedir;
rm -fR vagrant.dev.schoolbox.cloud;
tar xzf tls-vagrant.dev.schoolbox.cloud.tar.gz;
cp -f vagrant.dev.schoolbox.cloud/fullchain.pem /etc/nginx/certs/schoolbox-fullchain.crt;
cp -f vagrant.dev.schoolbox.cloud/privkey.pem /etc/nginx/certs/schoolbox-private.key;

echo "[SB][`date +%Y-%m-%d\ %X`] Set up video cronjobs"
cp -f /vagrant/tools/cron/schoolboxdev $cachedir/schoolboxdemo-latest-cron
sed -i 's/usr\/share\/schoolboxdev/vagrant/g' $cachedir/schoolboxdemo-latest-cron
sed -i 's/schoolboxdev/vagrant/g' $cachedir/schoolboxdemo-latest-cron
sed -i 's/var\/log\/vagrant/vagrant\/data/g' $cachedir/schoolboxdemo-latest-cron
sed -i 's/var\/lib\/vagrant/vagrant\/data/g' $cachedir/schoolboxdemo-latest-cron
sed -i 's/www-data/root/g' $cachedir/schoolboxdemo-latest-cron
cp -f $cachedir/schoolboxdemo-latest-cron /etc/cron.d/vagrant

echo "[SB][`date +%Y-%m-%d\ %X`] Installing grunt-cli if not already installed";
/vagrant/tools/grunt.sh;

echo "[SB][`date +%Y-%m-%d\ %X`] Installing npx if not already installed";
/vagrant/tools/npx.sh

echo "[SB][`date +%Y-%m-%d\ %X`] Building NPM";
cd $cachedir;
npmfile='';
npmlocksha=`sha1sum /vagrant/npm-shrinkwrap.json 2>/dev/null | awk '{print $1}'`;
if [ -f /vagrant/node_modules/npm.shrinkwrap.checksum ];
then
    currentnpmsha=`awk '{print $1}' /vagrant/node_modules/npm.shrinkwrap.checksum`;
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
                etag=`curl -I -H 'Referer: JIFrHts6WT7CeAU8xvlEFcG6VV3J89BO3CZMrQwF' https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-npm.tar.gz 2>/dev/null | grep ETag | cut -d'"' -f2`;
                if [ $? -eq 0 ];
                then
                    checketag=`grep $etag npm.etag.cache 2>/dev/null | wc -l`;
                    if [ $checketag -eq 0 ];
                    then
                        # New/updated version available - download it
                        echo "[SB][`date +%Y-%m-%d\ %X`] Downloading latest NPM build: $branch-npm.tar.gz";
                        wget --no-verbose --header="Referer: JIFrHts6WT7CeAU8xvlEFcG6VV3J89BO3CZMrQwF" https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-npm.tar.gz -O $branch-npm.tar.gz 2>&1;
                        if [ $? -eq 0 ];
                        then
                            # This is a new file, so remove old references
                            sed -i "/$branch-npm.tar.gz/d" npm.etag.cache 2>/dev/null;
                            sed -i "/$branch-npm.tar.gz/d" npm.cache 2>/dev/null;
                            # Add new file to etag cache
                            echo "$etag $branch-npm.tar.gz" >> npm.etag.cache;
                            # Cleanup for purity
                            rm -fR /vagrant/vagrantcache/node_modules 2>/dev/null;
                            # Extract checksum
                            tar xzf $branch-npm.tar.gz node_modules/npm.shrinkwrap.checksum;
                            npmchecksum=`awk '{print $1}' node_modules/npm.shrinkwrap.checksum`;
                            rm -fR /vagrant/vagrantcache/node_modules 2>/dev/null;
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
    cd /vagrant;
    if [ "$npmfile" != '' ];
    then
        if [ -f /vagrant/vagrantcache/$npmfile ];
        then
            # Extract content
            echo "[SB][`date +%Y-%m-%d\ %X`] Found matching NPM cache file ($npmlocksha) '$npmfile', now extracting";
            rm -fR /vagrant/node_modules 2>/dev/null;
            tar xzf /vagrant/vagrantcache/$npmfile;
        else
            # Still no luck, build from scratch with npm
            echo "[SB][`date +%Y-%m-%d\ %X`] Cache content:";
            cat /vagrant/vagrantcache/npm.cache;
            echo "[SB][`date +%Y-%m-%d\ %X`] No matching NPM cache found. Building with npm";
            /vagrant/tools/pkg-build-npm.sh 2>&1;
        fi
    else
        # Still no luck, build from scratch with npm
        echo "[SB][`date +%Y-%m-%d\ %X`] Cache content:";
        cat /vagrant/vagrantcache/npm.cache;
        echo "[SB][`date +%Y-%m-%d\ %X`] No matching NPM cache found. Building with npm";
        /vagrant/tools/pkg-build-npm.sh 2>&1;
    fi
fi
cd /vagrant;

echo "[SB][`date +%Y-%m-%d\ %X`] Building Javascript";
cd $cachedir;
bowerfile='';
bowerlocksha=`sha1sum /vagrant/bower.json 2>/dev/null | awk '{print $1}'`;
if [ -f /vagrant/www/javascript/components/bower.checksum ];
then
    currentbowersha=`awk '{print $1}' /vagrant/www/javascript/components/bower.checksum`;
    if [ "$currentbowersha" == "$bowerlocksha" ];
    then
        echo "[SB][`date +%Y-%m-%d\ %X`] Javascript build up to date, nothing to change ($bowerlocksha)";
        bowerfile='ignore';
    fi
fi

if [ "$bowerfile" != 'ignore' ];
then
    echo "[SB][`date +%Y-%m-%d\ %X`] Javascript build not correct or missing ($bowerlocksha)";
    check=`grep $bowerlocksha bower.cache 2>/dev/null | wc -l`;
    if [ $check -gt 0 ];
    then
        # Woo - we've located a matching cache file
        bowerfile=`grep $bowerlocksha bower.cache 2>/dev/null | head -n1 | cut -d' ' -f2-100`;
    else
        # No cache files match - boo hoo - check to see if there are new versions
        for branch in develop master;
        do
            # Check if we've already found a matching file
            if [ "$bowerfile" == '' ];
            then
                etag=`curl -I -H 'Referer: JIFrHts6WT7CeAU8xvlEFcG6VV3J89BO3CZMrQwF' https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-bower.tar.gz 2>/dev/null | grep ETag | cut -d'"' -f2`;
                if [ $? -eq 0 ];
                then
                    checketag=`grep $etag bower.etag.cache 2>/dev/null | wc -l`;
                    if [ $checketag -eq 0 ];
                    then
                        # New/updated version available - download it
                        echo "[SB][`date +%Y-%m-%d\ %X`] Downloading latest Javascript build: $branch-bower.tar.gz";
                        wget --no-verbose --header="Referer: JIFrHts6WT7CeAU8xvlEFcG6VV3J89BO3CZMrQwF" https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-bower.tar.gz -O $branch-bower.tar.gz 2>&1;
                        if [ $? -eq 0 ];
                        then
                            # This is a new file, so remove old references
                            sed -i "/$branch-bower.tar.gz/d" bower.etag.cache 2>/dev/null;
                            sed -i "/$branch-bower.tar.gz/d" bower.cache 2>/dev/null;
                            # Add new file to etag cache
                            echo "$etag $branch-bower.tar.gz" >> bower.etag.cache;
                            # Cleanup for purity
                            rm -fR /vagrant/vagrantcache/www/javascript/components 2>/dev/null;
                            # Extract checksum
                            tar xzf $branch-bower.tar.gz www/javascript/components/bower.checksum;
                            bowerchecksum=`awk '{print $1}' www/javascript/components/bower.checksum`;
                            rm -fR /vagrant/vagrantcache/www/javascript/components 2>/dev/null;
                            # Add new file to Javascript cache
                            echo "$bowerchecksum $branch-bower.tar.gz" >> bower.cache;
                            # Check if this is file we need
                            if [ "$bowerchecksum" == "$bowerlocksha" ];
                            then
                                bowerfile="$branch-bower.tar.gz";
                            fi
                        fi
                    fi
                fi
            fi
        done
    fi
    cd /vagrant;
    if [ "$bowerfile" != '' ];
    then
        if [ -f /vagrant/vagrantcache/$bowerfile ];
        then
            # Extract content
            echo "[SB][`date +%Y-%m-%d\ %X`] Found matching Javascript cache file ($bowerlocksha) '$bowerfile', now extracting";
            rm -fR /vagrant/www/javascript/components/ 2>/dev/null;
            tar xzf /vagrant/vagrantcache/$bowerfile;
        else
            # Still no luck, build from scratch with bower
            echo "[SB][`date +%Y-%m-%d\ %X`] Cache content:";
            cat /vagrant/vagrantcache/bower.cache;
            echo "[SB][`date +%Y-%m-%d\ %X`] No matching Javascript cache found. Building with bower";
            /vagrant/tools/pkg-build-bower.sh 2>&1;
        fi
    else
        # Still no luck, build from scratch with bower
        echo "[SB][`date +%Y-%m-%d\ %X`] Cache content:";
        cat /vagrant/vagrantcache/bower.cache;
        echo "[SB][`date +%Y-%m-%d\ %X`] No matching Javascript cache found. Building with bower";
        /vagrant/tools/pkg-build-bower.sh 2>&1;
    fi
fi
cd /vagrant;

echo "[SB][`date +%Y-%m-%d\ %X`] Installing grunt-cli if not already installed (a second time)";
/vagrant/tools/grunt.sh;

echo "[SB][`date +%Y-%m-%d\ %X`] Building JS files (Webpack)";
npm run build;

echo "[SB][`date +%Y-%m-%d\ %X`] Building JS files (Grunt)";
grunt build.js;

echo "[SB][`date +%Y-%m-%d\ %X`] Building CSS";
/vagrant/tools/pkg-build-sass.sh --sass;

echo "[SB][`date +%Y-%m-%d\ %X`] Loading all demo data complete";

echo "[SB][`date +%Y-%m-%d\ %X`] Run Notifications binding cache rebuild (in background)";
/vagrant/tools/notif/route/refresh-binding-cache.php --instance=vagrantx &

if [[ "$(readlink /proc/1/exe)" == */systemd ]];
then
    echo "[SB][`date +%Y-%m-%d\ %X`] Installing daemon systemd configurations";
    cp -f /vagrant/tools/init/schoolbox-*.service /lib/systemd/system/;
    sed -i 's/usr\/share\/schoolbox/vagrant/g' /lib/systemd/system/schoolbox-*d.service;
    systemctl daemon-reload;
    systemctl enable schoolbox-scheduled.service;
    systemctl enable schoolbox-routed.service;
    systemctl enable schoolbox-socketd.service;
    systemctl enable schoolbox-digestd.service;
    #systemctl enable schoolbox-belld.service;
else
    echo "[SB][`date +%Y-%m-%d\ %X`] Installing daemon upstart configurations";
    cp -f /vagrant/tools/init/upstart-scheduled.conf /etc/init/schoolbox-scheduled.conf;
    cp -f /vagrant/tools/init/upstart-scheduled.override /etc/init/schoolbox-scheduled.override;
    cp -f /vagrant/tools/init/upstart-socketd.conf /etc/init/schoolbox-socketd.conf;
    cp -f /vagrant/tools/init/upstart-socketd.override /etc/init/schoolbox-socketd.override;
    cp -f /vagrant/tools/init/upstart-digestd.conf /etc/init/schoolbox-digestd.conf;
    cp -f /vagrant/tools/init/upstart-digestd.override /etc/init/schoolbox-digestd.override;
    cp -f /vagrant/tools/init/upstart-routed.conf /etc/init/schoolbox-routed.conf;
    cp -f /vagrant/tools/init/upstart-routed.override /etc/init/schoolbox-routed.override;
    #cp -f /vagrant/tools/init/upstart-belld.conf /etc/init/schoolbox-belld.conf;
    #cp -f /vagrant/tools/init/upstart-belld.override /etc/init/schoolbox-belld.override;
fi

eplver=`dpkg -l schoolbox-epl | tail -n1 | awk {'print $3'}`;
echo "[SB][`date +%Y-%m-%d\ %X`] schoolbox-epl installed: $eplver";

ffmpegver=`dpkg -l schoolbox-ffmpeg | tail -n1 | awk {'print $3'}`;
echo "[SB][`date +%Y-%m-%d\ %X`] schoolbox-ffmpeg installed: $ffmpegver";

if [ "$enable_xdebug" == "true" ];
then
    echo "[SB][`date +%Y-%m-%d\ %X`] Enabling xdebug";
    if [ -f /vagrant/tools/xdebug.ini ];
    then
        echo "[SB][`date +%Y-%m-%d\ %X`] Using custom xdebug: tools/xdebug.ini";
        cp -f /vagrant/tools/xdebug.ini /etc/php/7.1/mods-available/xdebug.ini;
    else
        echo "[SB][`date +%Y-%m-%d\ %X`] Using default xdebug: tools/xdebug.default.ini";
        cp -f /vagrant/tools/xdebug.default.ini /etc/php/7.1/mods-available/xdebug.ini;
    fi
    if [ -d /etc/php/7.1/fpm/conf.d ];
    then
        ln -s /etc/php/7.1/mods-available/xdebug.ini /etc/php/7.1/fpm/conf.d/20-xdebug.ini >/dev/null 2>&1;
        ln -s /etc/php/7.1/mods-available/tideways.ini /etc/php/7.1/fpm/conf.d/20-tideways.ini >/dev/null 2>&1;
    fi
    ln -s /etc/php/7.1/mods-available/xdebug.ini /etc/php/7.1/cli/conf.d/20-xdebug.ini >/dev/null 2>&1;
    ln -s /etc/php/7.1/mods-available/tideways.ini /etc/php/7.1/cli/conf.d/20-tideways.ini >/dev/null 2>&1;

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
    rm -f /etc/php/7.1/cli/conf.d/20-xdebug.ini;
    rm -f /etc/php/7.1/cli/conf.d/20-tideways.ini;
    rm -f /etc/php/7.1/fpm/conf.d/20-xdebug.ini;
    rm -f /etc/php/7.1/fpm/conf.d/20-tideways.ini;
fi

echo "[SB][`date +%Y-%m-%d\ %X`] Ensure all services are running";
service schoolbox-epl restart > /dev/null 2>&1;
service php7.1-fpm restart > /dev/null 2>&1;
service nginx restart > /dev/null 2>&1;

echo "[SB][`date +%Y-%m-%d\ %X`] Removing system maintenance mode";
/vagrant/tools/maintenance/site_up.sh;

# call custom provisioning
if [ -f /vagrant/post-provision.sh ];
then
    echo "[SB][`date +%Y-%m-%d\ %X`] Running custom post provisioning";
    /vagrant/post-provision.sh 2>&1;
fi
