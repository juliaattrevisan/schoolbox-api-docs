INSERT INTO component_config_definition (
    component_definition_id,
    `key`,
    default_value,
    label,
    description
) VALUES (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Assessment\\Component\\Homepage\\Outline\\Controller'),
    'assessmentTypes',
    0,
    'Filter',
    null
);
