UPDATE component_definition
SET class = 'Custom\\Help\\Marketo\\Component\\Dashboard\\Controller'
WHERE class = 'Schoolbox\\Marketo\\Component\\Dashboard\\Controller';

-- Delete all traces of this component from non-help DBs

DELETE cci
FROM component_definition cd
INNER JOIN component_config_definition ccd ON cd.id = ccd.component_definition_id
INNER JOIN component_config_instance cci ON ccd.id = cci.component_config_definition_id
WHERE class = 'Custom\\Help\\Marketo\\Component\\Dashboard\\Controller'
AND (SELECT COUNT(*) FROM `config` WHERE `name` = 'site_location' and `value` = 'help') = 0;

DELETE ci
FROM component_definition cd
INNER JOIN component_config_definition ccd ON cd.id = ccd.component_definition_id
INNER JOIN component_config_instance cci ON ccd.id = cci.component_config_definition_id
INNER JOIN component_instance ci ON cci.component_instance_id = ci.id
WHERE class = 'Custom\\Help\\Marketo\\Component\\Dashboard\\Controller'
AND (SELECT COUNT(*) FROM `config` WHERE `name` = 'site_location' and `value` = 'help') = 0;

DELETE ccd
FROM component_definition cd
INNER JOIN component_config_definition ccd ON cd.id = ccd.component_definition_id
WHERE class = 'Custom\\Help\\Marketo\\Component\\Dashboard\\Controller'
AND (SELECT COUNT(*) FROM `config` WHERE `name` = 'site_location' and `value` = 'help') = 0;

DELETE cd
FROM component_definition cd
WHERE class = 'Custom\\Help\\Marketo\\Component\\Dashboard\\Controller'
AND (SELECT COUNT(*) FROM `config` WHERE `name` = 'site_location' and `value` = 'help') = 0;
