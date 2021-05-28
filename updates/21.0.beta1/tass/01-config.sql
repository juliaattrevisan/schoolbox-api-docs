-- Reorder the other two configs

-- Move these down two slots (we have two new vars coming in)
UPDATE `config`
SET sequence = sequence + 2
WHERE `name` IN (
    'external_tass_parent_id_length',
    'external_tass_app_code_studentcafe',
    'tass_staff_dir_award_exclude',
    'tass_staff_dir_award_mappings',
    'tass_staff_dir_position_text_exclude',
    'tass_staff_dir_position_title_exclude',
    'staff_directory_enabled',
    'parent_directory_enabled'
);

-- Insert our two new ones into the holes that were left behind

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
   (SELECT sequence+1 FROM config c WHERE name = 'external_tass_addnum_father')
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
   (SELECT sequence+2 FROM config c WHERE name = 'external_tass_addnum_father')
);
