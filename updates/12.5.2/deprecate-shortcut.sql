/* Shortcut is a now a deprecated dashboard component. Nuke it from orbit.
 * Conveniently, it doesn't have any config, so this didn't become a 4-table-joined delete
*/
DELETE component_definition, component_instance FROM component_definition
LEFT JOIN component_instance ON component_definition.`id` = component_instance.`component_definition_id`
WHERE component_definition.class = 'Component_Dashboard_ShortcutController';