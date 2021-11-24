INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES (
   'geosnapshot_redaction_key',
   '',
   'GeoSnapShot Redaction Key',
   'GeoSnapShot sends this header value when acquiring images to redact',
   (SELECT id FROM lu_config_field WHERE field = 'input'),
   '',
   (SELECT id FROM lu_config_type WHERE `type` = '3rd Party Services'),
   'true',
   'false',
   'false',
   'settings',
   (SELECT MAX(sequence) + 1 FROM config c WHERE `module` = 'settings' AND type_id = (SELECT id FROM lu_config_type WHERE `type` = '3rd Party Services'))
);
