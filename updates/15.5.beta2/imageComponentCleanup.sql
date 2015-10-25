/* Removes all instances on the align and pixel config options from the image component */

DELETE component_config_definition, component_config_instance FROM component_definition
JOIN component_config_definition
ON component_definition.id = component_definition_id
JOIN component_config_instance
ON component_config_definition_id = component_config_definition.id
WHERE class like 'Component_Homepage_ImageController' AND `key` IN ('align','pixel')