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

# MySQL will return something on error, and nothing on success.
output=$(mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_schema" -BNe "UPDATE config SET value=1 WHERE name='maintenance_mode'" 2>&1)

if [[ "$output" == '' ]]; then
  # IMPORTANT: The string output here is checked for in upgrade-instances.sh.
  echo "Maintenance mode enabled for instance '$instanceId'."
else
  echo "Could not enable maintenance mode for instance '$instanceId'."
fi
