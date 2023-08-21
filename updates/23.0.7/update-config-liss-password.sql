UPDATE `config`
SET `field_id` = (SELECT `id` FROM `lu_config_field` WHERE `field` = 'password')
WHERE `name` = 'external_liss_password';
