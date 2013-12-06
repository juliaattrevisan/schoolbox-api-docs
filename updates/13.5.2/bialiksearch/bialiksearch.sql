/* Restore bialik library search component
 */
INSERT INTO component_definition (`name`, `class`, `description`, `module`, component_category, visible, multiple) VALUES
('Bialik Search', 'Component_Homepage_BialikSearchController',  'Library Search',  'homepage',  5, 'true', 'false');

/* Config
 */
INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label) VALUES
((SELECT component_definition.id FROM component_definition WHERE class='Component_Homepage_BialikSearchController' LIMIT 1), 'componentname', 'Library Search', 'Heading');