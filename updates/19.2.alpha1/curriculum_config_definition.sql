UPDATE component_config_definition ccd
JOIN component_definition cd ON cd.id = ccd.component_definition_id
SET
    ccd.default_value = 'Curriculum Description'
WHERE cd.class = 'Schoolbox\\Curriculum\\Component\\CurriculumDetailsController' AND ccd.`key` = 'componentname';
