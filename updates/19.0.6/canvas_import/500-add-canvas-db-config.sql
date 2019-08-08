-- Canvas API key
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
    'canvas_data_api_key',
    '',
    'Canvas Data - API Key',
    'API Key for fetching data from Canvas API',
    (SELECT lcf.id FROM lu_config_field lcf WHERE lcf.field = 'input'),
    null,
    (SELECT lct.id FROM lu_config_type lct WHERE lct.type = 'External DB'),
    'true',
    'false',
    'false',
    'settings'
);


-- Canvas API secret
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
   'canvas_data_api_secret',
   '',
   'Canvas Data - API Secret',
   'API Secret for fetching data from Canvas API',
   (SELECT lcf.id FROM lu_config_field lcf WHERE lcf.field = 'input'),
   null,
   (SELECT lct.id FROM lu_config_type lct WHERE lct.type = 'External DB'),
   'true',
   'false',
   'false',
   'settings'
);
