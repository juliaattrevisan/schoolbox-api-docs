DELETE cci, ccd
FROM component_config_instance cci
INNER JOIN component_config_definition ccd ON cci.`component_config_definition_id` = ccd.`id`
INNER JOIN component_definition cd ON ccd.`component_definition_id` = cd.id
WHERE cd.class='Component_Dashboard_ScrollerController'
AND ccd.key = 'align';
