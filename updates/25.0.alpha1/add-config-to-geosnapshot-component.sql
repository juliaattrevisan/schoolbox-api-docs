INSERT INTO component_config_definition SET
component_definition_id = (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Geosnapshot\\Component\\Homepage\\Controller'),
`key`                   = 'albumName',
default_value           = '',
label                   = 'Album Name',
description             = NULL;

INSERT INTO component_config_definition SET
component_definition_id = (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Geosnapshot\\Component\\Dashboard\\Controller'),
`key`                   = 'albumName',
default_value           = '',
label                   = 'Album Name',
description             = NULL;

-- The default value for eventName was previously NULL.  Setting to '' to match albumName and for simpler error handling.
UPDATE component_config_definition
SET default_value = ''
WHERE component_definition_id IN (
    SELECT id
    FROM component_definition
    WHERE class IN (
        'Schoolbox\\Geosnapshot\\Component\\Homepage\\Controller',
        'Schoolbox\\Geosnapshot\\Component\\Dashboard\\Controller'
    )
)
AND `key`='eventName';
