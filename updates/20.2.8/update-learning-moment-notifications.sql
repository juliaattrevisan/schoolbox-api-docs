UPDATE msg_source
SET description = 'someone posted a learning moment for you'
WHERE `key` = 'posted'
  AND `role_type` = 'student'
  AND `namespace` = (SELECT id FROM msg_namespace WHERE msg_namespace.slug = 'evidence');
UPDATE msg_source
SET description = 'someone posted a learning moment'
WHERE `key` = 'posted'
  AND `role_type` = 'staff'
  AND `namespace` = (SELECT id FROM msg_namespace WHERE msg_namespace.slug = 'evidence');
