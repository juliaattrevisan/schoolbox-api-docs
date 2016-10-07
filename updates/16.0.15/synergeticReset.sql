SET @value = (SELECT CASE WHEN (SELECT TRIM(value) FROM `config` WHERE name = 'external_web_services_url') = '' THEN '0' ELSE '1' END AS value);

INSERT INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES (
  'synergetic_password_reset',
  @value,
  'Reset Community Portal Passwords',
  'Updates Synergetic Community Portal password when user changes their Schoolbox password.',
  3,
  '',
  7,
  'true',
  'false',
  'false'
);

DELETE FROM `config` WHERE `name` IN (
  'external_web_services_url',
  'external_vendor_token_guid',
  'external_vendor_password'
)