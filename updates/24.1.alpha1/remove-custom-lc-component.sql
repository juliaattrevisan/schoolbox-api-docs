SET @componentDefinitionId = (SELECT id FROM component_definition WHERE class='Custom\\Lc\\Survey\\Component\\Eportfolio\\Controller');

DELETE FROM component_config_instance
WHERE component_config_definition_id IN (
    SELECT id
    FROM component_config_definition
    WHERE component_definition_id = @componentDefinitionId
);

DELETE FROM component_instance
WHERE component_definition_id = @componentDefinitionId;

DELETE FROM component_config_definition
WHERE component_definition_id = @componentDefinitionId;

DELETE FROM component_definition
WHERE id = @componentDefinitionId;
