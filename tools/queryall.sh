#!/bin/bash

error() {
        # Display an error message in red then exit with error code
        echo -e "\033[38;5;1m"
        echo -e "Error: $errmsg\n";
        echo -e "\033[39m";
        exit 1;
}

readconf() {
        # Collect Credentials from $tempconfile
        tempsqluser=`grep "\"SQL_user" $tempconffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
        tempsqlpasswd=`grep "\"SQL_passwd" $tempconffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
        tempsqlhost=`grep "\"SQL_host" $tempconffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
        tempsqldb=`grep "\"SQL_db" $tempconffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
}

echo -e "\nDo you want to query *all* schools' message config? (y/n): \c" && read confirm;
if [ "$confirm" != "y" ];
then
        errmsg="Mass Query Cancelled";
        error;
fi
q='SELECT g.group_type, min_level, max_level, default_level, COUNT(*) FROM msg_route_perm rp LEFT JOIN groups g ON rp.role_id = g.id GROUP BY group_type, min_level, max_level, default_level ORDER BY group_type, min_level, default_level, max_level;';
msgconfig='';
for server in `grep Host ~/.ssh/config | egrep '(_live)' | awk '{print $2}'`;
do
  fnm="../data/msg.$server.conf";

  if [ -f $fnm ]; then
    echo "$server already queried, skipping"
  else
    echo "$server..."
    tempconffile='/tmp/tempconf.conf';
    ssh $server "cat /etc/schoolbox/conf.php" > $tempconffile 2>/dev/null;
    readconf;
    msgconfig=`ssh $server "mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe \"$q\""; 2>/dev/null`;
    echo -e "$msgconfig" > $fnm;
    msgconfig='';
  fi

done