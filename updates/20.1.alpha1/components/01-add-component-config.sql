-- Add a new Component Config Definition to all components

INSERT INTO
component_config_definition (`component_definition_id`, `key`, `default_value`, `label`, `description`)
(
    SELECT
        id, 'showComponentTitle', 1, 'Show Title', null
    FROM component_definition
);

