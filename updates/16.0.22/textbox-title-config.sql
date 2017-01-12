UPDATE schoolbox.component_config_definition
SET `key` = 'textBoxComponentName'
WHERE `key` = 'componentname'
AND component_definition_id = (SELECT `id` FROM component_definition WHERE class = 'Component_Homepage_TextBoxController')