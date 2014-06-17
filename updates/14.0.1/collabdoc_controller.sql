/* Update an incorrect field name in Etherpad homepage component
 * This causes a phplib
 */
UPDATE component_config_definition
SET `key` = 'compactMode'
WHERE component_definition_id IN (
	SELECT id
	FROM component_definition
	WHERE class = 'Component_Homepage_EtherpadController'
)
AND label = 'Compact Mode';
