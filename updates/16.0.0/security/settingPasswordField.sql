INSERT INTO `lu_config_field` (`field`) VALUES ('password');

UPDATE config SET config.field_id = (SELECT lu_config_field.id from lu_config_field WHERE lu_config_field.field like 'password') WHERE  name IN('ldap_pass', 'external_db_passwd', 'mail_smtp_password', 'proxy_pass')
