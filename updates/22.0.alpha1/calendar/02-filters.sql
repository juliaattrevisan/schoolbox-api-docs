-- Allow users to select various filters
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
    'calendarFilter',
    null,
    'Filter',
    null
);
