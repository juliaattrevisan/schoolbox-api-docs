#!/usr/bin/env bash
# Simple script to place schoolbox into maintenance mode.

instanceId=${INSTANCE_ID:-0}

if [[ "$instanceId" == '0' ]]; then
  echo 'INSTANCE_ID not set.'
fi

sql_user=$(INSTANCE_ID="$instanceId" schoolbox-read-instances --option SQL_user);
sql_pass=$(INSTANCE_ID="$instanceId" schoolbox-read-instances --option SQL_passwd);
sql_host=$(INSTANCE_ID="$instanceId" schoolbox-read-instances --option SQL_host);
sql_schema=$(INSTANCE_ID="$instanceId" schoolbox-read-instances --option SQL_db);

# On success, MySQL will output nothing, and exit with status code 0.
output=$(mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_schema" -BNe "UPDATE config SET value=1 WHERE name='maintenance_mode'" 2>&1)
statusCode=$?

if [[ "$output" == '' && statusCode -eq 0 ]]; then
  # IMPORTANT: The string output here is checked for in upgrade-instances.sh.
  echo "Maintenance mode enabled for instance '$instanceId'."
  exit 0
else
  echo "Could not enable maintenance mode for instance '$instanceId'."
  exit 1
fi
