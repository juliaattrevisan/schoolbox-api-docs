-- update existing component instances
UPDATE component_config_instance SET value='A Text Box component' WHERE component_config_definition_id = (
    SELECT id FROM component_config_definition WHERE default_value = 'An textbox component'
);
-- update component definition
update component_config_definition set default_value='A Text Box component' where default_value='An textbox component';
