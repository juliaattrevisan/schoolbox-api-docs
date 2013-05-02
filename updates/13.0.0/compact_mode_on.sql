-- Delete compact mode from components that do not use it (Folder, Forum)

DELETE component_config_definition FROM component_definition JOIN component_config_definition ON component_config_definition.`component_definition_id`= component_definition.`id`
WHERE `key` = 'compactMode' AND class IN ('Component_Homepage_FolderListController','Component_Homepage_ForumListController');

-- Add compact mode to audio component
INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label) SELECT id, 'compactMode', 1, 'Compact Mode' FROM component_definition WHERE class = 'Component_Homepage_AudioController';

-- Default compact mode to on for more concise output
UPDATE component_config_definition SET default_value = '1' WHERE `key` = 'compactMode';
