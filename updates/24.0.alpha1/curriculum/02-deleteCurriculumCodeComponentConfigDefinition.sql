SELECT @componentDefinitionId := id
    FROM component_definition
    WHERE class = 'Schoolbox\\Curriculum\\Component\\CurriculumOverviewController';

DELETE cci
FROM component_config_instance   cci
JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
WHERE ccd.component_definition_id = @componentDefinitionId
AND   ccd.`key`                   = 'curriculumCode';

DELETE ccd
FROM component_config_definition ccd
WHERE ccd.component_definition_id = @componentDefinitionId
AND   ccd.`key`                   = 'curriculumCode';
