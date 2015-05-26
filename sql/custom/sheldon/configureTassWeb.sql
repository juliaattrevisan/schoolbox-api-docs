/**
 * TASSWeb Configuration for SHCL
 */
UPDATE `config`
SET `value` = 'https://tass.sheldoncollege.com/'
WHERE `name` = 'external_staff_portal'
LIMIT 1;

UPDATE `config`
SET `value` = 'https://tass.sheldoncollege.com/tassweb/external/sso_gateway.cfm'
WHERE `name` = 'external_tass_sso_server'
LIMIT 1;

UPDATE `config`
SET `value` = 'i2!cjd0xs'
WHERE `name` = 'external_tass_sso_token_key'
LIMIT 1;

UPDATE `config`
SET `value` = 'pl'
WHERE `name` = 'external_tass_app_code_parent_lounge'
LIMIT 1;

UPDATE `config`
SET `value` = 'TASSWEB'
WHERE `name` = 'external_tass_app_code_tassweb'
LIMIT 1;

UPDATE `config`
SET `value` = 'TIA'
WHERE `name` = 'external_tass_app_code_teacherkiosk'
LIMIT 1;

UPDATE `config`
SET `value` = 'WB'
WHERE `name` = 'external_tass_app_code_webbook'
LIMIT 1;
