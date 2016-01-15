UPDATE `component_definition`, `component_config_definition` SET `name` = 'Surveys and Forms', default_value = 'Surveys and Forms'
WHERE `class` = 'Component_Homepage_SurveyController'
AND `component_definition`.`id` = `component_config_definition`.`component_definition_id`
AND `key` = 'componentname';

