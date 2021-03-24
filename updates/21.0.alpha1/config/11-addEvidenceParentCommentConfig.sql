INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES (
           'evidence_parent_comment',
           1,
           'Allow Parents to comment on Learning Moments',
           'If set to ON, parents will be able to comment on learning moments of their own children',
           (SELECT id FROM lu_config_field WHERE field = 'boolean'),
           '',
           (SELECT id FROM lu_config_type WHERE `type` = 'Policy'),
           'true',
           'false',
           'false',
           'settings',
           (SELECT MAX(sequence) + 1 FROM config c WHERE `module` = 'settings' AND type_id = (SELECT id FROM lu_config_type WHERE `type` = 'Policy'))
       );
