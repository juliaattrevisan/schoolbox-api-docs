INSERT INTO `config` (
  name,
  value,
  title,
  description,
  field_id,
  options,
  type_id,
  editable,
  hidden,
  required
) VALUES (
  'synergetic_portal_create_account',
  '0',
  'Reset Community Portal Password Creates Accounts',
  'When a parent resets their password and Community Portal integration is activated, this will create a Community Portal account for them automatically if one does not exist.',
  3,
  '',
  7,
  'true',
  'false',
  'false'
);