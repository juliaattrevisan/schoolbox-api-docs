INSERT INTO component_config_definition (
    component_definition_id,
    `key`,
    default_value,
    label,
    description
) VALUES (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Assessment\\Component\\Homepage\\Outline\\Controller'),
    'assessmentTypes',
    '{"dueWork":"1","quiz":"1","task":"1","project":"1","lti":"1","lessonPlan":"1"}',
    'Filter',
    null
);
