-- Canvas API key
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
    'canvas_url',
    'https://staloysius.instructure.com',
    'Canvas URL',
    'URL to access Canvas',
    (SELECT lcf.id FROM lu_config_field lcf WHERE lcf.field = 'input'),
    null,
    (SELECT lct.id FROM lu_config_type lct WHERE lct.type = 'External DB'),
    'true',
    'true',
    'false',
    'settings'
);
