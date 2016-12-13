INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required) VALUES ('fixtures_enabled', '0', 'Enable Fixtures Module', 'Is the fixtures enabled?', 3, '', 11, 'false', 'false', 'false');

UPDATE `component_definition` SET `visible` = 'false' WHERE `class` = 'Schoolbox\\Fixtures\\Component\\Homepage\\Controller';
UPDATE `component_definition` SET `visible` = 'false' WHERE `class` = 'Schoolbox\\Fixtures\\Component\\Dashboard\\Controller';
