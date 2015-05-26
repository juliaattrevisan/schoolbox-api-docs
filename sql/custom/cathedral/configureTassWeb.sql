/**
 * TASSWeb Configuration for TCS
 */
UPDATE `config`
SET `value` = 'https://tassportal.cathedral.qld.edu.au/'
WHERE `name` = 'external_staff_portal'
LIMIT 1;

UPDATE `config`
SET `value` = 'https://tassportal.cathedral.qld.edu.au/tassweb/external/sso_gateway.cfm'
WHERE `name` = 'external_tass_sso_server'
LIMIT 1;

UPDATE `config`
SET `value` = 'L@g00n1917'
WHERE `name` = 'external_tass_sso_token_key'
LIMIT 1;

UPDATE `config`
SET `value` = 'pl'
WHERE `name` = 'external_tass_app_code_parent_lounge'
LIMIT 1;

UPDATE `config`
SET `value` = 'ec'
WHERE `name` = 'external_tass_app_code_tassweb'
LIMIT 1;

UPDATE `config`
SET `value` = 'ec'
WHERE `name` = 'external_tass_app_code_teacherkiosk'
LIMIT 1;

UPDATE `config`
SET `value` = 'ec'
WHERE `name` = 'external_tass_app_code_webbook'
LIMIT 1;
