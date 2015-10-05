#!/bin/bash

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

conffile=`readlink -f $fullpath/../www/conf/conf.php`;

echo "Auto linking tool for linking Staff and Parent accounts";
echo "";

if [ ! -f $conffile ];
then
	echo "New installation or conf file missing";
	exit;
fi

echo "Using db credentials from: "`readlink -f $conffile`;


if [ ! -z $1 ];
then
	if [ "$1" == "--test" ];
	then
		test='test';
		echo "";
		echo "Running in test mode - no changes will be made";
	fi
fi

# Collect Credentials
sqluser=`grep "\"SQL_user" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqlpasswd=`grep "\"SQL_passwd" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqlhost=`grep "\"SQL_host" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
sqldb=`grep "\"SQL_db" $conffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;

echo "";
echo "Looking for Staff accounts with a matching Parent account";

mysqldb="mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb";

numberacc=`$mysqldb -BNe "
	SELECT count(*) FROM user u
	JOIN user uu ON u.synergy_id = uu.synergy_id
	WHERE	u.synergy_id != '' AND u.synergy_id is not NULL
		AND u.synergy_id=uu.synergy_id
		AND uu.group_id IN (SELECT id FROM groups WHERE group_type='parent')
		AND u.group_id IN (SELECT id FROM groups WHERE group_type='staff')"`;

echo "Matching accounts: $numberacc";
echo "";
echo "Attempting to link accounts";

stafflist=`$mysqldb -BNe "
	SELECT u.id FROM user u
        JOIN user uu ON u.synergy_id = uu.synergy_id
        WHERE   u.synergy_id != '' AND u.synergy_id is not NULL
                AND u.synergy_id=uu.synergy_id
                AND uu.group_id IN (SELECT id FROM groups WHERE group_type='parent')
                AND u.group_id IN (SELECT id FROM groups WHERE group_type='staff')"`;

for staffid in `echo "$stafflist"`;
do
	parentid=`$mysqldb -BNe "
		SELECT uu.id FROM user u
		JOIN user uu ON u.synergy_id = uu.synergy_id
		WHERE
			u.id = '$staffid' AND
			u.synergy_id=uu.synergy_id AND
			uu.group_id IN (SELECT id FROM groups WHERE group_type='parent') AND
			u.group_id IN (SELECT id FROM groups WHERE group_type='staff')"`;
	fullname=`$mysqldb -BNe "SELECT CONCAT(firstname,' ',lastname,' (',synergy_id,')') FROM user WHERE id = '$staffid'"`;
	echo "Linking: $staffid to $parentid - $fullname";

	# Check if running in test mode before making changes
	if [ -z $test ];
	then
		$mysqldb -e "INSERT INTO user_link VALUES ('$staffid','$parentid',NULL,NULL)" >/dev/null 2>&1;
		$mysqldb -e "INSERT INTO user_link VALUES ('$parentid','$staffid',NULL,NULL)" >/dev/null 2>&1;
	fi
done;

echo "";
echo "Finished attempting to link accounts";
