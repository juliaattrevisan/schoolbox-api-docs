/* Add sortable fields
 */
INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label, description)
VALUES ((SELECT id FROM component_definition WHERE class = 'Component_Homepage_VideoController'), 'sort', 'date', 'Sort by', NULL);

INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label, description)
VALUES ((SELECT id FROM component_definition WHERE class = 'Component_Homepage_VideoController'), 'sortdir', 'desc', 'Direction', NULL);