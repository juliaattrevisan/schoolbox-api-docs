-- Add the event name config option to Dashboard component
INSERT INTO component_config_definition
    (`component_definition_id`, `key`, `default_value`, `label`, `description`)
VALUES (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Geosnapshot\\Component\\Dashboard\\Controller'),
    'eventName',
    null,
    'Event Name',
    null
);