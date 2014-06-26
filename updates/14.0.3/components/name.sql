INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label)
SELECT id, 'componentname', 'Group Managers', 'Heading'  FROM component_definition WHERE class = 'Component_Homepage_ManagersController';

INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label)
SELECT id, 'componentname', 'Members', 'Heading'  FROM component_definition WHERE class = 'Component_Homepage_MembersController';
