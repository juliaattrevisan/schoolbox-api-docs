INSERT INTO component_config_definition SET
component_definition_id = (SELECT id FROM component_definition WHERE class = 'Component_Homepage_FolderListController'),
`key`                   = 'sort',
default_value           = 'Name',
label                   = 'Sort By',
description             = NULL;

INSERT INTO component_config_definition SET
component_definition_id = (SELECT id FROM component_definition WHERE class = 'Component_Homepage_FolderListController'),
`key`                   = 'sortdir',
default_value           = 'asc',
label                   = 'Sort Direction',
description             = NULL;
