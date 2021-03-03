INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES (
           'email_banner_image',
           '',
           'Email Banner Image',
           'This image will be included in the banner of all digest and notification emails. We recommend an image size of 670x100px',
           (SELECT id FROM lu_config_field WHERE field = 'file'),
           '',
           (SELECT id FROM lu_config_type WHERE `type` = 'Skin Config'),
           'true',
           'false',
           'false',
           'settings',
           (SELECT MAX(sequence) + 1 FROM config c WHERE `module` = 'settings' AND type_id = (SELECT id FROM lu_config_type WHERE `type` = 'Skin Config'))
       );

UPDATE config SET sequence = sequence + 1 WHERE type_id = (SELECT id FROM lu_config_type WHERE `type` = 'Policy') AND sequence > 42;

INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES (
           'digest_email_intro',
           '',
           'Digest Email Introduction',
           'If provided, this text will be displayed at the beginning of all email digests sent to users.',
           (SELECT id FROM lu_config_field WHERE field = 'html'),
           '',
           (SELECT id FROM lu_config_type WHERE `type` = 'Policy'),
           'true',
           'false',
           'false',
           'settings',
           (SELECT sequence + 1 FROM config c WHERE `name` = 'digest_subject')
       );
