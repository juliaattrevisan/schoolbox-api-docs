INSERT INTO config(
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
             'schoolbox_api_url',
             'https://api.schoolbox.cloud',
             'Schoolbox Api URL',
             'The URL for the schoolbox api gateway',
             (SELECT id FROM lu_config_field WHERE field = 'input'),
             '',
             (SELECT id FROM lu_config_type WHERE type = 'resources'),
             'false',
             'true',
             'false'
         );
