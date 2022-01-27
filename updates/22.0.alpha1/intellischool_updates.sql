INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required,
                              module, sequence)
VALUES ('intellischool_task_dashboard_base_url', null, 'Intellischool Task Dashboard Base URL',
        'The base URL for this instance''s Intellischool task adherence dashboards',
        (SELECT id FROM lu_config_field WHERE field = 'input'), null,
        (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'), 'true', 'true',
        'false', 'settings', null);

