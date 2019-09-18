INSERT INTO `config` (name, value, title, description,field_id, options, type_id, editable, hidden, required, module)
VALUES ('spelling_checking_language','en_AU', 'Spelling Checking Language', 'This enables admin to set the spelling checking language.',
        (SELECT id from lu_config_field  where field = 'selectKey'),'en_AU;English (Australian):en_GB;English (British):en_US;English (USA)',
        (SELECT id from lu_config_type where type='localisation'), 'true', 'false', 'true','settings');
