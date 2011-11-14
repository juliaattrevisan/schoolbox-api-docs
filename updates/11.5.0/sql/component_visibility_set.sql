/*
This SQL file is designed to bring all schools in line with the current setup for components and only allow custom built modules 
to be run by the school whom paid for them, especially when they will only work in their environment.
*/

/* Ensures all components are available excepted those which are excluded. */
UPDATE component_definition SET visible = 'true';

/* Ensures the  Component "Intranet" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Wenona Intranet';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Wenona Intranet' AND config.value = 'wenona';

/* Ensures the  Component "Parent Helpdesk" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Parent Helpdesk';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Parent Helpdesk' AND config.value = 'wenona';

/* Ensures the  Component "Helpdesk" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Helpdesk';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Helpdesk' AND config.value = 'mgs';

/* Ensures the  Component "Suggestion" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Suggestion';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Suggestion' AND config.value = 'SCHOOL';

/* Ensures the  Component "Activity" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Activity';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Activity' AND config.value = 'SCHOOL';

/* Ensures the  Component "Shortcut" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Shortcut';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Shortcut' AND config.value = 'cathedral';

/* Ensures the  Component "Tutorial" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Tutorial';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Tutorial' AND config.value = 'mgs';

/* Ensures the  Component "Child Subject" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Child Subject';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Child Subject' AND config.value = 'mggs';

/* Deletes all Active Components that are supposed to be hidden */
DELETE FROM component_instance WHERE component_definition_id IN (SELECT id FROM component_definition WHERE visible = 'false');
