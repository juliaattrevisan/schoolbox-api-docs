/* SB-4765 */
UPDATE `config` SET `type_id`=(SELECT `id` FROM `lu_config_type` WHERE `type`='3rd Party Services') WHERE `name`='crocodoc_api_token';
UPDATE `config` SET `editable`='true' WHERE `name` = 'weather_api_key';
