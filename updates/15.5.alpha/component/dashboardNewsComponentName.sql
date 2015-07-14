-- Give news a name
INSERT IGNORE INTO component_config_definition (
    `component_definition_id`,
    `key`,
    `default_value`,
    `label`
) VALUES (
    (SELECT id FROM component_definition WHERE `class`='Component_Dashboard_NewsController'),
    'componentname',
    'News Headlines',
    'Heading'
);
