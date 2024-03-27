INSERT INTO component_config_definition (
    component_definition_id,
    `key`,
    default_value,
    label,
    description
) VALUES (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Curriculum\\Component\\CurriculumOverviewController'),
    'curriculumTreeFilter',
    0,
    'Curriculum Tree Filter',
    null
);
