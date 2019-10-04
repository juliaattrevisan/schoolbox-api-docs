INSERT INTO `config` (name, value, title, description,field_id, options, type_id, editable, hidden, required, module)
VALUES ('spellcheck_language','en_AU', 'Spell Checker Language', 'The system will automatically check the spelling of text in the WYSIWYG editor using this language.',
        (SELECT id from lu_config_field  where field = 'selectKey'),'en_AU;English (Australian):en_GB;English (British):en_US;English (USA)',
        (SELECT id from lu_config_type where type='localisation'), 'true', 'false', 'true','settings');
