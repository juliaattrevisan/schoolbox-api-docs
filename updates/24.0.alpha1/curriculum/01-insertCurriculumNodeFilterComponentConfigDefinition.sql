-- First ensure the component definition exists in the first place
INSERT IGNORE INTO component_definition (
    name,
    class,
    description,
    module,
    component_category,
    enabled,
    visible,
    multiple,
    msg_namespace_slug,
    msg_namespace_slug_suffix,
    config_copy
) VALUES (
    'Curriculum Overview',
    'Schoolbox\\Curriculum\\Component\\CurriculumOverviewController',
    'Shows an overview of a curriculum code that has been attached to this homepage, as well as any sub-codes that this code might have.',
    'homepage',
    3,
    1,
    'true',
    'true',
    null,
    null,
    null
);

-- And ensure the component's config definitions exist in the first place
SELECT @componentDefinitionId := id FROM component_definition WHERE class = 'Schoolbox\\Curriculum\\Component\\CurriculumOverviewController';
INSERT IGNORE INTO component_config_definition (
    component_definition_id,
    `key`,
    default_value,
    label,
    description
) VALUES (
    @componentDefinitionId,
    'collapse',
    '0',
    'Collapse Content',
    null
), (
    @componentDefinitionId,
    'componentname',
    'Curriculum Overview ',
    'Heading',
    null
), (
    @componentDefinitionId,
    'showComponentTitle',
    '1',
    'Show Title to Read-Only Users',
    null
);

-- Then add the new config definition
INSERT INTO component_config_definition (
    component_definition_id,
    `key`,
    default_value,
    label,
    description
) VALUES (
    @componentDefinitionId,
    'curriculumTreeFilter',
    0,
    'Curriculum Tree Filter',
    null
);
