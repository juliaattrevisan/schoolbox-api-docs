/* Add ability to change the Heading value for a Group Managers component
 */
INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label)
SELECT id, 'componentname', 'Group Managers', 'Heading'  FROM component_definition WHERE class = 'Component_Homepage_ManagersController';

/* Add ability to change the Heading value for a Group Members component
 */
INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label)
SELECT id, 'componentname', 'Members', 'Heading'  FROM component_definition WHERE class = 'Component_Homepage_MembersController';

/* Add ability to change the Heading value for a Twitter component
 */
INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label)
SELECT id, 'componentname', 'Twitter', 'Heading'  FROM component_definition WHERE class = 'Component_Homepage_TwitterController';