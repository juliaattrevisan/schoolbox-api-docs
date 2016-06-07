INSERT INTO component_config_definition
(
    `component_definition_id`,
    `key`,
    `default_value`,
    `label`,
    `description`
) VALUES (
    (select id from component_definition where class = 'Component_Dashboard_ChildSubjectController'),
    'componentname',
    'My Students',
    'Heading',
    ''
);
