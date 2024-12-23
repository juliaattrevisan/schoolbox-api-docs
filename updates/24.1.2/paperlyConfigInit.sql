### init the paperly config with common system values

update config set value = '103.9.64.235' where name = 'paperly_agent_ip_whitelist';

update config set value = 'paperly' where name = 'paperly_agent_external_db_user_override';