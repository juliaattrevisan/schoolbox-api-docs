/* Add ability to change the Heading value for a Twitter component
 */
INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label)
SELECT id, 'componentname', 'Twitter', 'Heading'  FROM component_definition WHERE class = 'Component_Homepage_TwitterController'