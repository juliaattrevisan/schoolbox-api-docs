-- Identify component instances that need fixing
--
-- SELECT
--     cd.name,
--     cci.value,
--     ci.identifier AS folderId
-- FROM component_definition cd
-- INNER JOIN component_config_definition ccd ON cd.id = ccd.component_definition_id
-- INNER JOIN component_config_instance cci ON ccd.id = cci.component_config_definition_id
-- INNER JOIN component_instance ci on cci.component_instance_id = ci.id
-- WHERE cd.class = 'Component_Homepage_RubricController'
-- AND component_config_definition_id = (
--     SELECT id
--     FROM component_config_definition
--     WHERE component_config_definition.default_value = 'Course Criteria'
--     AND component_config_definition.key = 'componentname'
-- )

-- Just update the default value
UPDATE component_config_definition ccd
SET default_value = 'Rubric Capabilities and Indicators'
WHERE ccd.key = 'componentname'
AND ccd.default_value = 'Course Criteria'
LIMIT 1;

-- Update the description in the component palette
UPDATE component_definition
SET
    name = 'Rubric Capabilities and Indicators',
    description = 'View and edit the rubric capablities and indicators for this subject or course.'
WHERE class = 'Component_Homepage_RubricController'
LIMIT 1;
