ALTER TABLE `config` CHANGE `type` `type` ENUM('Site','Security','Policy','Paths','Resources','Email','Welcome','Localisation','External DB','Colours','Board Leave','Modules','3rd Party Services') CHARSET utf8 COLLATE utf8_general_ci NULL;
UPDATE `config` SET `type`='Modules' WHERE `name`='boarder_leave_enabled';
UPDATE `config` SET `type`='Modules' WHERE `name`='mobile_enabled';
UPDATE `config` SET `type`='3rd Party Services' WHERE `name`='xml_cache_timeout';
UPDATE `config` SET `type`='3rd Party Services' WHERE `name`='welcome_rss_url';
UPDATE `config` SET `type`='3rd Party Services' WHERE `name`='welcome_rss_limit';
UPDATE `config` SET `type`='3rd Party Services' WHERE `name`='weather_enable';
UPDATE `config` SET `type`='3rd Party Services' WHERE `name`='weather_location';
UPDATE `config` SET `type`='3rd Party Services' WHERE `name`='recaptcha_private_api_key';
UPDATE `config` SET `type`='3rd Party Services' WHERE `name`='recaptcha_public_api_key';
UPDATE `config` SET `type`='3rd Party Services' WHERE `name`='plagscan_api_key';
UPDATE `config` SET `type`='3rd Party Services' WHERE `name`='plagscan_username';
UPDATE `config` SET `type`='3rd Party Services' WHERE `name`='weather_api_key';
UPDATE `config` SET `type`='3rd Party Services' WHERE `name`='weather_api_caching';

