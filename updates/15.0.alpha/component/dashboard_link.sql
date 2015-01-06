-- Remove dashboard LinkController db stuff

-- Find out if it's even being used
--SELECT  *
--FROM component_definition
--LEFT JOIN component_instance ON component_definition.id = component_instance.`component_definition_id`
--LEFT JOIN component_config_instance ON component_instance.id = component_config_instance.component_instance_id
--LEFT JOIN component_config_definition ON component_config_instance.component_config_definition_id = component_config_definition.id
--WHERE component_definition.`class` =  'Component_Dashboard_LinkController'

DELETE
FROM component_config_instance
WHERE component_instance_id IN (
	SELECT id
	FROM component_instance
	WHERE component_definition_id IN (
		SELECT id
		FROM component_definition
		WHERE class = 'Component_Dashboard_LinkController'
	)
);

DELETE
FROM component_config_definition
WHERE component_definition_id IN (
		SELECT id
		FROM component_definition
		WHERE class = 'Component_Dashboard_LinkController'
);

DELETE
FROM component_instance
WHERE component_definition_id IN (
	SELECT id
	FROM component_definition
	WHERE class = 'Component_Dashboard_LinkController'
);

DELETE
FROM component_definition
WHERE class = 'Component_Dashboard_LinkController';