INSERT INTO `config`
(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES (
   'external_tass_address_description_mother',
   'Mother',
   'TASS Address Description for Mother',
   'Provide the value that your TASS instance uses to identify mothers in Communication Rules.',
   (SELECT id FROM lu_config_field WHERE field = 'input'),
   null,
   (SELECT id FROM lu_config_type WHERE type = 'External DB'),
   'true',
   'false',
   'false',
   'settings',
   (SELECT MAX(sequence)+1 FROM config c WHERE type_id = (
       SELECT id FROM lu_config_type WHERE type = 'External DB'
   ))
);

INSERT INTO `config`
(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES (
   'external_tass_address_description_father',
   'Father',
   'TASS Address Description for Father',
   'Provide the value that your TASS instance uses to identify fathers in Communication Rules.',
   (SELECT id FROM lu_config_field WHERE field = 'input'),
   null,
   (SELECT id FROM lu_config_type WHERE type = 'External DB'),
   'true',
   'false',
   'false',
   'settings',
   (SELECT MAX(sequence)+1 FROM config c WHERE type_id = (
       SELECT id FROM lu_config_type WHERE type = 'External DB'
   ))
);
