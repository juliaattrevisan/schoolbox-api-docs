-- Adds a Compact Mode component config to My Students dashboard component

INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label, description)
VALUES (
    (SELECT id FROM component_definition WHERE `class` = 'Component_Dashboard_ChildSubjectController' LIMIT 1),
    'compactMode',
    0,
    'Compact Mode',
    null
);
