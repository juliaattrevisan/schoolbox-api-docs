INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required,
                              module, sequence)
VALUES ('email_signature', null, 'Email Signature',
        'Inserts this signature into emails created inside schoolbox. available tokens are [title] [firstname] [lastname] [email] [position] ',
        (SELECT id from lu_config_field where field = 'html'), null, (SELECT id from lu_config_type where type = 'Email'), 'true', 'false', 'false', 'settings',
        (SELECT MAX(sequence) + 1 FROM config c WHERE `module` = 'settings' AND type_id = (SELECT id FROM lu_config_type WHERE `type` = 'Email')));

