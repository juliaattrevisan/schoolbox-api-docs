-- Rename the course instance list to what it actually is, the course outline
UPDATE `component_definition`
SET `class` = 'Component_Homepage_CourseOutlineController'
WHERE `class` = 'Component_Homepage_CourseInstanceController';
