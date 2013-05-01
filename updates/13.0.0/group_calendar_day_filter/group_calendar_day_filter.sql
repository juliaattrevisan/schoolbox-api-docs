-- Change event list's existing 'size' filter to 'days'
UPDATE component_definition AS cd
	INNER JOIN component_config_definition AS cc ON cd.id = cc.component_definition_id
SET cc.label='Days', cc.key='days' 
WHERE cd.class = 'Component_Homepage_EventListController' AND cc.key = 'size';

-- And add a new filter for size.
INSERT INTO component_config_definition (
	component_definition_id, 
	`key`, 
	label, 
	default_value
)
VALUES (
	(SELECT cd.id FROM component_definition cd WHERE cd.class='Component_Homepage_EventListController' LIMIT 1), 
	'size', 
	'Limit', 
	0
);


