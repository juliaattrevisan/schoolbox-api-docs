INSERT INTO `config`
(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES (
   'clipboard_endpoint_host',
   '',
   'Clipboard Endpoint Host',
   'The Clipboard API is located here.',
   (SELECT id FROM lu_config_field WHERE field = 'input'),
   null,
   (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
   'true',
   'false',
   'false',
   'settings',
   (SELECT MAX(sequence)+1 FROM config c)
);


INSERT INTO `config`
(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES (
   'clipboard_endpoint_token',
   '',
   'Clipboard Endpoint Token',
   'The authentication token granted by the Clipboard app that allows Clipboard events to be imported into Fixtures.',
   (SELECT id FROM lu_config_field WHERE field = 'input'),
   null,
   (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
   'true',
   'false',
   'false',
   'settings',
   (SELECT MAX(sequence)+1 FROM config c)
);
