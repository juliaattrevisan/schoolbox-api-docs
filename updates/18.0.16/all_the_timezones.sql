UPDATE config set field_id = 10, options = 'a:1:{s:6:"mapper";s:40:"Schoolbox\\Admin\\Timezone\\TimezoneFactory";}' where name = 'date_timezone';
UPDATE config set value = 'Australia/Melbourne' where name = 'date_timezone' and value = 'Australia/Victoria';
UPDATE config set value = 'Australia/Sydney' where name = 'date_timezone' and value = 'Australia/NSW';
UPDATE config set value = 'Australia/Brisbane' where name = 'date_timezone' and value = 'Australia/Queensland';
UPDATE config set value = 'Australia/Perth' where name = 'date_timezone' and value = 'Australia/West';
UPDATE config set value = 'Australia/Adelaide' where name = 'date_timezone' and value = 'Australia/South';
