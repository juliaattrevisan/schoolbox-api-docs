INSERT INTO config(name, value, title, description, field_id, editable, hidden, required, module) VALUES (
  'audience_to_folder_users_teachers',
  '0',
  'Audience to teachers of users in class/group',
  'If enabled, users can send news to all teachers of users in a class/group',
  (SELECT id FROM lu_config_field WHERE field = 'boolean'),
  'true',
  'true',
  'true',
  'comms'
);
