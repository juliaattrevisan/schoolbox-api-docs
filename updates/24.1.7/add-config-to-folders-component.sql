INSERT INTO component_config_definition SET
component_definition_id = (SELECT id FROM component_definition WHERE class = 'Component_Homepage_FolderListController'),
`key`                   = 'sort',
default_value           = NULL,
label                   = 'Sort by',
description             = NULL;
