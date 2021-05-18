INSERT INTO `config`
(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES (
   'yandex_enabled',
   0,
   'Enable Yandex Translation for Parents',
   'Provide the Yandex website translation tool, to provide automatic language translation.',
   (SELECT id FROM lu_config_field WHERE field = 'boolean'),
   null,
   (SELECT id FROM lu_config_type WHERE type = 'Modules'),
   'true',
   'true',
   'false',
   'settings',
   (SELECT MAX(sequence)+1 FROM config c WHERE type_id = (
       SELECT id FROM lu_config_type WHERE type = 'Modules'
   ))
);
