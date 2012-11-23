UPDATE `config`
SET `description` = 'By default display weather at this location. This value can be formatted as Country/City; latitude,longitude; airport code; or IP address.'
WHERE `name` = 'weather_location';

UPDATE `config` SET `type` = '3rd Party Services'
	WHERE `name` = 'weather_enable'
	OR `name` =  'weather_location'
	OR `name` =  'weather_api_key'
	OR `name` =  'weather_api_caching'
	OR `name` =  'plagscan_api_key'
	OR `name` =  'plagscan_username'
	OR `name` =  'welcome_rss_url'
	OR `name` =  'welcome_rss_limit'
	OR `name` =  'recaptcha_private_api_key'
	OR `name` =  'recaptcha_public_api_key';

UPDATE `config` SET `hidden` = 'true', `editable` = 'false'
	WHERE `name` = 'welcome_rss_url'
	OR `name` = 'welcome_rss_limit';

DELETE FROM `config` WHERE `name` = 'welcome_image_folder';

INSERT INTO `config`
  (`name`, `value`, `title`, `description`, `field`, `type`,`editable`, `hidden`, `required`)
VALUES
  ('xml_cache_timeout', '3000', 'XML Update Frequency', 'How long xml data should be cached for before being re-queried in seconds', 'input', '3rd Party Services', 'true', 'false', 'true');