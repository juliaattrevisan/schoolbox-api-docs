-- Previously, this value was hardcoded (kinda)
-- This change enables the admin to set how far back to show results for
INSERT INTO `component_config_definition` (`component_definition_id`, `key`, `default_value`, `label`, `description`)
VALUES ('19', 'days', '180', 'Days', 'Maximum days to show results for');
