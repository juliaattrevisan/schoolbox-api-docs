-- add eportfolio to config type table so we can use the /settings/EPortfolio route
INSERT INTO lu_config_type (type, sequence)
    VALUES ('ePortfolio', 21);

-- add eportfolio subject / groups config
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required,
                              module, sequence)
VALUES ('eportfolio_show_subjects_and_groups', '1', 'Show Subjects and Groups on ePortfolio',
        'If toggled to ON, a students classes and group membership will be featured on the ePortfolio homepage and be visible to all users.',
        (select id from lu_config_field where field = 'boolean'), null, (select id from lu_config_type where type = 'ePortfolio'), 'true', 'false', 'false', 'settings', null);
