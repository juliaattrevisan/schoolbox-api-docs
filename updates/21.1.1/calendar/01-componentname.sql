-- Allow users to change the heading
INSERT INTO component_config_definition (
    component_definition_id,
    `key`,
    default_value,
    label,
    description
) VALUES (
    (
        SELECT id
        FROM component_definition
        WHERE class = 'Schoolbox\\Calendar\\Component\\Dashboard\\Controller'
        LIMIT 1
    ),
    'componentname',
    'Calendar',
    'Heading',
    null
);
