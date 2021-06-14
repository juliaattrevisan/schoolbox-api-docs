/**
 * Re-add the event name config option to dashboard and homepage components.
 *
 * (Previous attempts might fail as `component_config_definition.default_value`
 *  may not have been nullable.)
 */
INSERT IGNORE INTO component_config_definition
    (`component_definition_id`, `key`, `default_value`, `label`, `description`)
VALUES (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Geosnapshot\\Component\\Dashboard\\Controller'),
    'eventName',
    null,
    'Event Name',
    null
), (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Geosnapshot\\Component\\Homepage\\Controller'),
    'eventName',
    null,
    'Event Name',
    null
);
