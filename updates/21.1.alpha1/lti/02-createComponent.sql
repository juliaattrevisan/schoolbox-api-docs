INSERT INTO component_definition (
    name,
    class,
    description,
    module,
    component_category,
    visible,
    multiple,
    msg_namespace_slug,
    msg_namespace_slug_suffix,
    config_copy
) VALUES (
    'LTI Assessments',
    'Schoolbox\\LTI\\Assessment\\Component\\Homepage\\Controller',
    'Output all LTI assessments for this homepage.',
    'homepage',
    3, # Learning
    'true',
    'false',
    null,
    null,
    null
);
INSERT INTO component_config_definition (
    component_definition_id,
    `key`,
    default_value,
    label,
    description
) VALUES (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\LTI\\Assessment\\Component\\Homepage\\Controller'),
    'size',
    0,
    'Limit',
    null
), (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\LTI\\Assessment\\Component\\Homepage\\Controller'),
    'compactMode',
    1,
    'Compact Mode',
    null
), (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\LTI\\Assessment\\Component\\Homepage\\Controller'),
    'collapse',
    0,
    'Collapse Content',
    null
), (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\LTI\\Assessment\\Component\\Homepage\\Controller'),
    'showClosed',
    0,
    'Show Closed',
    'Whether to include closed assessments in the list'
), (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\LTI\\Assessment\\Component\\Homepage\\Controller'),
    'showComponentTitle',
    1,
    'Show Title to Read-Only Users',
    null
), (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\LTI\\Assessment\\Component\\Homepage\\Controller'),
    'componentname',
    'LTI Assessments',
    'Heading',
    null
);
