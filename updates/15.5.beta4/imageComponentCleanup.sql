/* Removes all instances on the align and pixel config options from the image component */

DELETE component_config_definition, component_config_instance FROM component_definition
LEFT JOIN component_config_definition
ON component_definition.id = component_definition_id
LEFT JOIN component_config_instance
ON component_config_definition_id = component_config_definition.id
WHERE class IN ('Component_Homepage_ImageController','Component_Homepage_RandImageController','Component_Homepage_ScrollerController', 'Component_Dashboard_ScrollerController') AND `key` IN ('align','pixel')

