UPDATE component_definition SET visible = 'false' WHERE class IN ('Component_Homepage_ManageClassController','Component_Homepage_ManageFolderController');

DELETE component_instance FROM component_instance, component_definition
WHERE component_definition.id = component_instance.component_definition_id
AND class IN ('Component_Homepage_ManageClassController','Component_Homepage_ManageFolderController');
