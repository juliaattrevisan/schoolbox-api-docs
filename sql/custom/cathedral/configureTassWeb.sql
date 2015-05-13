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
