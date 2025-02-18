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
