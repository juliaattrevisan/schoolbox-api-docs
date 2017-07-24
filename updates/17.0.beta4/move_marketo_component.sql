UPDATE component_definition
SET class = 'Custom\\Help\\Marketo\\Component\\Dashboard\\Controller'
WHERE class = 'Schoolbox\\Marketo\\Component\\Dashboard\\Controller';

DELETE FROM component_definition
WHERE class = 'Custom\\Help\\Marketo\\Component\\Dashboard\\Controller'
AND (SELECT COUNT(*) FROM `config` WHERE `name` = 'site_location' and `value` = 'help') = 0;
