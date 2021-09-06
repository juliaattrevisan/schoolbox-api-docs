INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES (
           'remove_image_exif',
           0,
           'Remove exif data from image',
           'If set to ON, exif data will be removed from image on upload',
           (SELECT id FROM lu_config_field WHERE field = 'boolean'),
           '',
           (SELECT id FROM lu_config_type WHERE `type` = 'Policy'),
           'true',
           'false',
           'false',
           'settings',
           (SELECT MAX(sequence) + 1 FROM config c WHERE `module` = 'settings' AND type_id = (SELECT id FROM lu_config_type WHERE `type` = 'Policy'))
       );
