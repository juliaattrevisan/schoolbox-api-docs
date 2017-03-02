#!/bin/bash
cachedir='/vagrant/vagrantcache';
mkdir -p $cachedir;
cd $cachedir;

# Defaults
download_url="vagrant.alaress-dev.com.au";
external_db="synergetic";
external_db_host="172.31.15.10";

# Get options
if [ ! -z $1 ]; then download_url=$1; fi;
if [ ! -z $2 ]; then demo_data_version=$2; else echo "Please provide demo_data_version"; exit 1; fi;
if [ ! -z $3 ]; then external_db=$3; fi;
if [ ! -z $4 ]; then external_db_host=$4; fi;

echo "[SB][`date +%Y-%m-%d\ %X`] Setting up vagrant with options:";
echo "[SB][`date +%Y-%m-%d\ %X`] Provision Script: vagrant-provision-1404-php55.sh";
echo "[SB][`date +%Y-%m-%d\ %X`] Download URL: $download_url";
echo "[SB][`date +%Y-%m-%d\ %X`] Demo Data Version: $demo_data_version";
echo "[SB][`date +%Y-%m-%d\ %X`] External DB: $external_db";
echo "[SB][`date +%Y-%m-%d\ %X`] External DB Host: $external_db_host";


phpversion=`php -v | head -n1 | awk '{print $2}' | cut -c1-3`;
echo "[SB][`date +%Y-%m-%d\ %X`] Checking for correct PHP version - current: $phpversion";
if [ "$phpversion" != "7.1" ];
then
    add-apt-repository ppa:ondrej/php
	apt-get update 2>&1 >/dev/null;
	apt-get install -y php7.1 php7.1-cli php7.1-dev php7.1-opcache php7.1-bcmath php7.1-bz2 php7.1-common php7.1-curl php7.1-dba php7.1-enchant php7.1-gd php7.1-imap php7.1-json php7.1-ldap php7.1-mbstring php7.1-mcrypt php7.1-pspell php7.1-readline php7.1-soap php7.1-xml php7.1-zip php7.1-mysql libapache2-mod-php7.1 php-apcu php-imagick php-redis php7.1-sybase;
	apt-get install -y php-xdebug;
	a2dismod php5;
	a2enmod php7.1;
fi

echo "[SB][`date +%Y-%m-%d\ %X`] Retrieving latest Demo Data files";
curl http://$download_url/schoolboxdemo-$demo_data_version-sha1.txt > schoolboxdemo-$demo_data_version-sha1.txt 2>/dev/null
if [ $? -eq 0 ];
then
	mv -f schoolboxdemo-$demo_data_version-sha1.txt schoolboxdemo-$demo_data_version-sha1-cached.txt;
	rm -f schoolboxdemo-$demo_data_version-sha1.txt;
else
	echo "[SB][`date +%Y-%m-%d\ %X`] Unable to retrive $demo_data_version sha1 list, continuing with current";
fi
if [ ! -f schoolboxdemo-$demo_data_version-sha1-cached.txt ];
then
	echo "[SB][`date +%Y-%m-%d\ %X`] Unable to retrieve $demo_data_version demo data. Halting provisioning.";
	exit 1;
fi
while read line;
do
	demofile=`echo "$line" | awk {'print $2'}`;
	demosha1=`echo "$line" | awk {'print $1'}`;
	currentsha1=`sha1sum $demofile 2>/dev/null || echo 'false'`;
	if [ "$line" != "$currentsha1" ];
	then
		echo "[SB][`date +%Y-%m-%d\ %X`] Downloading $demofile";
		curl -L http://$download_url/$demofile > $demofile;
	fi
done < schoolboxdemo-$demo_data_version-sha1-cached.txt;

echo "[SB][`date +%Y-%m-%d\ %X`] Updating timezone data";
mysql_tzinfo_to_sql /usr/share/zoneinfo 2>/dev/null | mysql --defaults-extra-file=/root/.my.cnf mysql 2>/dev/null;

echo "[SB][`date +%Y-%m-%d\ %X`] Removing current Schoolbox MySQL database";
mysql --defaults-extra-file=/root/.my.cnf -e "DROP DATABASE IF EXISTS schoolbox";

echo "[SB][`date +%Y-%m-%d\ %X`] Removing current Schoolbox Data directory";
rm /vagrant/data/* -fR;

echo "[SB][`date +%Y-%m-%d\ %X`] Setting up browscap cache";
mkdir -p /vagrant/tools/browscap;
rm -f /vagrant/tools/browscap/browscap.ini;
touch /vagrant/tools/browscap/browscap.ini;
cp -f $cachedir/cache-2016-01-14.txt /vagrant/tools/browscap/cache.php;

echo "[SB][`date +%Y-%m-%d\ %X`] Loading Schoolbox MySQL Demo data";
mysql --defaults-extra-file=/root/.my.cnf -e "CREATE DATABASE schoolbox CHARACTER SET 'utf8' COLLATE 'utf8_general_ci';";
mysql --defaults-extra-file=/root/.my.cnf schoolbox < $cachedir/schoolboxdemo-$demo_data_version.sql;

mysql --defaults-extra-file=/root/.my.cnf schoolbox -e "UPDATE config SET value='$external_db' WHERE name='external_type'";
mysql --defaults-extra-file=/root/.my.cnf schoolbox -e "UPDATE config SET value='$external_db_host' WHERE name='external_db_host'";

echo "[SB][`date +%Y-%m-%d\ %X`] Creating Schoolbox data directories";
cd /vagrant;
tar -xzf $cachedir/schoolboxdemo-$demo_data_version.tar.gz;
rm -rf /vagrant/data/cache;
/vagrant/tools/create-data-dirs.sh /vagrant/data;

echo "[SB][`date +%Y-%m-%d\ %X`] Creating Schoolbox conf file";
cp -f /vagrant/www/conf/tarball.conf.php /vagrant/www/conf/conf.php;
sed -i 's/xxxxxxxx/vagrantx/g' /vagrant/www/conf/conf.php;
sed -i 's/xxxxxx/tnp879/g' /vagrant/www/conf/conf.php;

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
                                etag=`curl -I https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-vendor-php-dev.tar.gz 2>/dev/null | grep ETag | cut -d'"' -f2`;
                                if [ $? -eq 0 ];
                                then
                                        checketag=`grep $etag vendor.etag.cache 2>/dev/null | wc -l`;
                                        if [ $checketag -eq 0 ];
                                        then
                                                # New/updated version available - download it
                                                echo "[SB][`date +%Y-%m-%d\ %X`] Downloading latest vendor build: $branch-vendor-php-dev.tar.gz";
                                                curl https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-vendor-php-dev.tar.gz > $branch-vendor-php-dev.tar.gz;
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

echo "[SB][`date +%Y-%m-%d\ %X`] Building SQL updates";
cd tools;
./build-sql-update.sh >/dev/null;
cd /vagrant;

echo "[SB][`date +%Y-%m-%d\ %X`] Generating data dir sql fixes";
cp -f $cachedir/schoolboxdemo-vagrant-$demo_data_version.sql /tmp/schoolboxdemo-vagrant-$demo_data_version.sql;
/vagrant/tools/generate-dir-sql.sh /vagrant/data /vagrant >> /tmp/schoolboxdemo-vagrant-$demo_data_version.sql;

echo "[SB][`date +%Y-%m-%d\ %X`] Applying vagrant SQL fixes";
mysql --defaults-extra-file=/root/.my.cnf schoolbox < /tmp/schoolboxdemo-vagrant-$demo_data_version.sql

echo "[SB][`date +%Y-%m-%d\ %X`] Upgrading demo data with latest SQL updates";
mkdir /var/lib/vagrant -p > /dev/null;
/vagrant/tools/update-sql.sh;
rm -f /vagrant/updates/*/scripts_list > /dev/null;
rm -R /var/lib/vagrant > /dev/null;
rm -f /vagrant/www/conf/pre*conf.php;

echo "[SB][`date +%Y-%m-%d\ %X`] Generating data dir sql fixes (post version upgrade)";
cp -f $cachedir/schoolboxdemo-vagrant-$demo_data_version.sql /tmp/schoolboxdemo-vagrant-$demo_data_version.sql;
/vagrant/tools/generate-dir-sql.sh /vagrant/data /vagrant >> /tmp/schoolboxdemo-vagrant-$demo_data_verison.sql;

echo "[SB][`date +%Y-%m-%d\ %X`] Applying vagrant SQL fixes (post version upgrade)";
mysql --defaults-extra-file=/root/.my.cnf schoolbox < /tmp/schoolboxdemo-vagrant-$demo_data_version.sql

echo "[SB][`date +%Y-%m-%d\ %X`] Applying UTF8 schema patch";
/vagrant/tools/schema/collation.sh;

echo "[SB][`date +%Y-%m-%d\ %X`] Removing existing apache vhosts";
rm -f /etc/apache2/sites-enabled/*;

echo "[SB][`date +%Y-%m-%d\ %X`] Enabling Schoolbox apache vhost";
sed 's/usr\/share\/schoolbox/vagrant/g' /vagrant/tools/apache-vhost-schoolbox > /etc/apache2/sites-available/schoolbox.conf;
sed -i 's/php_flag\ display_startup_errors\ off/php_flag\ display_startup_errors\ on/g' /etc/apache2/sites-available/schoolbox.conf;
sed -i 's/php_flag\ display_errors\ off/php_flag\ display_errors\ on/g' /etc/apache2/sites-available/schoolbox.conf;
sed -i 's/php_flag\ html_errors\ off/php_flag\ html_errors\ on/g' /etc/apache2/sites-available/schoolbox.conf;
sed -i 's/var\/log\/apache2/vagrant/g' /etc/apache2/sites-available/schoolbox.conf;
a2ensite schoolbox.conf
echo 'Listen 8000' > /etc/apache2/sites-available/schoolbox-dav.conf;
sed 's/usr\/share\/schoolbox/vagrant/g' /vagrant/tools/apache-vhost-schoolbox-webdav >> /etc/apache2/sites-available/schoolbox-dav.conf;
sed -i 's/php_flag\ display_startup_errors\ off/php_flag\ display_startup_errors\ on/g' /etc/apache2/sites-available/schoolbox-dav.conf;
sed -i 's/php_flag\ display_errors\ off/php_flag\ display_errors\ on/g' /etc/apache2/sites-available/schoolbox-dav.conf;
sed -i 's/php_flag\ html_errors\ off/php_flag\ html_errors\ on/g' /etc/apache2/sites-available/schoolbox-dav.conf;
sed -i 's/var\/log\/apache2/vagrant/g' /etc/apache2/sites-available/schoolbox-dav.conf;
sed -i 's/*:80/*:8000/g' /etc/apache2/sites-available/schoolbox-dav.conf;
a2ensite schoolbox-dav.conf

echo "[SB][`date +%Y-%m-%d\ %X`] Enabling Schoolbox nginx vhost";
cp -f /vagrant/tools/nginx-vhost-schoolbox-websocket.conf /etc/nginx/conf.d/;
sed -i 's/var\/log\/nginx/vagrant/g' /etc/nginx/conf.d/nginx-vhost-schoolbox-websocket.conf;

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
                                etag=`curl -I https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-npm.tar.gz 2>/dev/null | grep ETag | cut -d'"' -f2`;
                                if [ $? -eq 0 ];
                                then
                                        checketag=`grep $etag npm.etag.cache 2>/dev/null | wc -l`;
                                        if [ $checketag -eq 0 ];
                                        then
                                                # New/updated version available - download it
                                                echo "[SB][`date +%Y-%m-%d\ %X`] Downloading latest NPM build: $branch-npm.tar.gz";
                                                curl https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-npm.tar.gz > $branch-npm.tar.gz;
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
                                etag=`curl -I https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-bower.tar.gz 2>/dev/null | grep ETag | cut -d'"' -f2`;
                                if [ $? -eq 0 ];
                                then
                                        checketag=`grep $etag bower.etag.cache 2>/dev/null | wc -l`;
                                        if [ $checketag -eq 0 ];
                                        then
                                                # New/updated version available - download it
                                                echo "[SB][`date +%Y-%m-%d\ %X`] Downloading latest Javascript build: $branch-bower.tar.gz";
                                                curl https://s3-ap-southeast-2.amazonaws.com/schoolbox-ci/$branch-bower.tar.gz > $branch-bower.tar.gz;
                                                if [ $? -eq 0 ];
                                                then
                                                        # This is a new file, so remove old references
                                                        sed -i "/$branch-bower.tar.gz/d" bower.etag.cache 2>/dev/null;
                                                        sed -i "/$branch-bower.tar.gz/d" bower.cache 2>/dev/null;
                                                        # Add new file to etag cache
                                                        echo "$etag $branch-bower.tar.gz" >> bower.etag.cache;
                                                        # Cleanup for purity
                                                        rm -fR /vagrant/vagrantcache/node_modules 2>/dev/null;
                                                        # Extract checksum
                                                        tar xzf $branch-bower.tar.gz www/javascript/components/bower.checksum;
                                                        bowerchecksum=`awk '{print $1}' www/javascript/components/bower.checksum`;
                                                        rm -fR /vagrant/vagrantcache/node_modules 2>/dev/null;
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
                        rm -fR /vagrant/node_modules 2>/dev/null;
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

echo "[SB][`date +%Y-%m-%d\ %X`] Building CSS";
/vagrant/tools/pkg-build-sass.sh --sass;

echo "[SB][`date +%Y-%m-%d\ %X`] Loading all demo data complete";

echo "[SB][`date +%Y-%m-%d\ %X`] Run Notifications binder (in background)";
cd /vagrant/tools/mq;
./binder.php &

echo "[SB][`date +%Y-%m-%d\ %X`] Install schoolbox-scheduled";
cp -f /vagrant/tools/init/upstart-scheduled.conf /etc/init/schoolbox-scheduled.conf;
cp -f /vagrant/tools/init/upstart-scheduled.override /etc/init/schoolbox-scheduled.override;

echo "[SB][`date +%Y-%m-%d\ %X`] Install schoolbox-socketd";
cp -f /vagrant/tools/init/upstart-socketd.conf /etc/init/schoolbox-socketd.conf;
cp -f /vagrant/tools/init/upstart-socketd.override /etc/init/schoolbox-socketd.override;

echo "[SB][`date +%Y-%m-%d\ %X`] Install schoolbox-digestd";
cp -f /vagrant/tools/init/upstart-digestd.conf /etc/init/schoolbox-digestd.conf;
cp -f /vagrant/tools/init/upstart-digestd.override /etc/init/schoolbox-digestd.override;

echo "[SB][`date +%Y-%m-%d\ %X`] Install schoolbox-rerouted";
cp -f /vagrant/tools/init/upstart-rerouted.conf /etc/init/schoolbox-rerouted.conf;
cp -f /vagrant/tools/init/upstart-rerouted.override /etc/init/schoolbox-rerouted.override;

#echo "[SB][`date +%Y-%m-%d\ %X`] Install schoolbox-belld";
#cp -f /vagrant/tools/init/upstart-belld.conf /etc/init/schoolbox-belld.conf;
#cp -f /vagrant/tools/init/upstart-belld.override /etc/init/schoolbox-belld.override;

eplver=`dpkg -l schoolbox-epl | tail -n1 | awk {'print $3'}`;
echo "[SB][`date +%Y-%m-%d\ %X`] schoolbox-epl installed: $eplver";

ffmpegver=`dpkg -l schoolbox-ffmpeg | tail -n1 | awk {'print $3'}`;
echo "[SB][`date +%Y-%m-%d\ %X`] schoolbox-ffmpeg installed: $ffmpegver";

if [ "`apache2 -v | grep Apache | cut -d'/' -f2 | cut -d'.' -f1-2`" == "2.4" ];
then
	echo "[SB][`date +%Y-%m-%d\ %X`] Apache is v2.4.x, changing access config to match";
	sed -i 's/Order\ allow,deny//g' /etc/apache2/sites-available/*.conf;
	sed -i 's/Allow\ from\ all/Require\ all\ granted/g' /etc/apache2/sites-available/*.conf;
fi

echo "[SB][`date +%Y-%m-%d\ %X`] Ensure all services are running";
service schoolbox-epl restart > /dev/null 2>&1;
service apache2 restart > /dev/null 2>&1;
service nginx restart > /dev/null 2>&1;
service schoolbox-scheduled restart > /dev/null 2>/dev/null &
service schoolbox-socketd restart > /dev/null 2>/dev/null &
service schoolbox-digestd restart > /dev/null 2>/dev/null &
service schoolbox-rerouted restart > /dev/null 2>/dev/null &
#service schoolbox-belld restart > /dev/null 2>/dev/null &

# call custom provisioning
if [ -f /vagrant/post-provision.sh ];
then
	echo "[SB][`date +%Y-%m-%d\ %X`] Running custom post provisioning";
	/vagrant/post-provision.sh 2>&1;
fi

for eth in `ip addr | grep inet | grep -v inet6 | grep global | grep -v eth0 | awk {'print $2'} | cut -d/ -f1`;
do
	echo "[SB][`date +%Y-%m-%d\ %X`] Website URL => http://$eth/";
	echo "[SB][`date +%Y-%m-%d\ %X`] WebDav URL => http://$eth:8000/";
done
