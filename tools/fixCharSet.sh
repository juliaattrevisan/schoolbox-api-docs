#!/bin/bash
if [ `which apache2 2>/dev/null | wc -l` -gt 0 ];
then
	webserver='apache2';
else
	if [ `which httpd 2>/dev/null | wc -l` -gt 0 ];
	then
		webserver='httpd';
	else
		echo "Unable to work out web server";
		exit 1;
	fi
fi

echo "Fixing DB character set to UTF-8";
service $webserver stop 2>&1;

filepath=`readlink -f $0`;
fullpath=${filepath%/*};
conffile=`readlink -f $fullpath/../www/conf/conf.php`;

# Collect Credentials
sqluser=`grep "\"SQL_user" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqlpasswd=`grep "\"SQL_passwd" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqlhost=`grep "\"SQL_host" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqldb=`grep "\"SQL_db" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;

# Collect current SQL version
sqlver=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='site_version' \G;" | grep 'value' | cut -d' ' -f2`;

if [ -z $sqlver ];
then
	echo "Unable to access database. Please check your database credentials.";
	exit 1;
fi

mysqldump -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb --default-character-set=latin1 --skip-set-charset > /tmp/fixcharset.sql;
mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb --default-character-set=utf8 < /tmp/fixcharset.sql;
mysql -u $sqluser -p$sqlpasswd -h $sqlhost -e "ALTER DATABASE $sqldb CHARACTER SET utf8 COLLATE utf8_general_ci;";
service $webserver start 2>&1;

#always exit without error to allow installs to continue
exit 0;
