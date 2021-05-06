-- Adds a Geosnapshot homepage component
INSERT INTO `component_definition`
    (`name`, `class`, `description`, `module`, `component_category`, `enabled`, `visible`, `multiple`)
VALUES (
    'GeoSnapShot',
    'Schoolbox\\Geosnapshot\\Component\\Homepage\\Controller',
    'Displays a GeoSnapShot event gallery on a homepage, ensure you have configured your GeoSnapShot school code before using this component.',
    'homepage',
    '4',
    '1',
    'true',
    'true'
);

-- Add the event name config option to Dashboard component
INSERT INTO component_config_definition
(`component_definition_id`, `key`, `default_value`, `label`, `description`)
VALUES (
   (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Geosnapshot\\Component\\Homepage\\Controller'),
   'eventName',
   null,
   'Event Name',
   null
);

-- Allow users to change the heading
INSERT INTO component_config_definition (
    component_definition_id,
    `key`,
    default_value,
    label,
    description
) VALUES (
    (
        SELECT id
        FROM component_definition
        WHERE class = 'Schoolbox\\Geosnapshot\\Component\\Homepage\\Controller'
        LIMIT 1
    ),
    'componentname',
    'GeoSnapShot',
    'Heading',
    null
);

-- Allow users to hide/show the heading to readonly users
INSERT INTO
    component_config_definition (`component_definition_id`, `key`, `default_value`, `label`, `description`)
    (
        SELECT
            id, 'showComponentTitle', 1, 'Show Title to Read-Only Users', null
        FROM component_definition
        WHERE `module` = 'homepage'
          AND `class` = 'Schoolbox\\Geosnapshot\\Component\\Homepage\\Controller'
    );