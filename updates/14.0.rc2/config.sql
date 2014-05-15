/* SB-4765 */
UPDATE `config` SET `type_id`='10' WHERE `name`='crocodoc_api_token';
UPDATE `config` SET `editable`='true' WHERE `name` = 'weather_api_key';
