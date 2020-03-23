SELECT
    cd.name,
    cci.value,
    ci.identifier AS folderId
FROM component_definition cd
INNER JOIN component_config_definition ccd ON cd.id = ccd.component_definition_id
INNER JOIN component_config_instance cci ON ccd.id = cci.component_config_definition_id
INNER JOIN component_instance ci on cci.component_instance_id = ci.id
WHERE cd.class = 'Component_Homepage_RubricController'
AND component_config_definition_id = (
    SELECT id
    FROM component_config_definition
    WHERE component_config_definition.default_value = 'Course Criteria'
    AND component_config_definition.key = 'componentname'
)
AND cci.value IS NOT NULL
