-- Rename the course task list to what it actually is, the course builder
UPDATE `component_definition`
SET `class` = 'Component_Homepage_CourseBuilderController'
WHERE `class` = 'Component_Homepage_CourseTaskListController';
