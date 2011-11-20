/*
This SQL file is designed to bring all schools in line with the current setup for components and only allow custom built modules 
to be run by the school whom paid for them, especially when they will only work in their environment.
*/

/* Ensures all components are available excepted those which are excluded. */
UPDATE component_definition SET visible = 'true';

/* Ensures the  Component "Intranet" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Wenona Intranet';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Wenona Intranet' AND config.value IN (Select value from config where name = 'site_location' and value = 'wenona');

/* Ensures the  Component "Parent Helpdesk" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Parent Helpdesk';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Parent Helpdesk' AND config.value IN (Select value from config where name = 'site_location' and value = 'mgs');
/* Ensures the  Component "Helpdesk" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Helpdesk';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Helpdesk' AND config.value IN (Select value from config where name = 'site_location' and value = 'mgs');

/* Ensures the  Component "Suggestion" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Suggestion';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Suggestion' AND config.value IN (Select value from config where name = 'site_location' and value = 'jpc');

/* Ensures the  Component "Shortcut" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Shortcut';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Shortcut'  AND config.value IN (Select value from config where name = 'site_location' and value = 'cathedral');

/* Ensures the  Component "Tutorial" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Tutorial';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Tutorial' AND config.value IN (Select value from config where name = 'site_location' and value = 'ormond');

/* Ensures the  Component "Organisation" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Organisation';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Organisation' AND config.value IN (Select value from config where name = 'site_location' and value = 'jpc');

/* Ensures the  Component "Primary" isnt visible to other schools. */
UPDATE component_definition,config SET visible = 'false' WHERE component_definition.name = 'Primary';
UPDATE component_definition,config SET visible = 'true' WHERE component_definition.name = 'Primary' AND config.value IN (Select value from config where name = 'site_location' and value = 'mgs');

/* Deletes all Active Components that are supposed to be hidden */
DELETE FROM component_instance WHERE component_definition_id IN (SELECT id FROM component_definition WHERE visible = 'false');
