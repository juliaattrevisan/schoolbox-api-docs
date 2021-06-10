UPDATE component_definition SET name = 'Text Box' WHERE class IN('Component_Dashboard_TextboxController','Component_Eportfolio_TextboxController');

UPDATE component_config_definition SET label = 'Text Box' where `key` = 'textbox';
