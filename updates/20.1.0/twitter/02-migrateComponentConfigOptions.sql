-- Just use the "url" config, and migrate the existing "username" setting into it
UPDATE IGNORE component_config_definition ccd
INNER JOIN component_definition cd ON ccd.component_definition_id = cd.id
SET ccd.key         = 'url',
    ccd.label       = 'URL or Username',
    ccd.description = 'The URL of the Twitter content to show, or a username to show a feed for'
WHERE cd.class IN ('Schoolbox\\Twitter\\Component\\Homepage\\Controller', 'Schoolbox\\Twitter\\Component\\Dashboard\\Controller')
AND   ccd.key = 'username';

-- Get rid of "keywords" completely
DELETE cci FROM component_config_instance cci
INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
INNER JOIN component_definition        cd  ON ccd.component_definition_id = cd.id
WHERE cd.class IN ('Schoolbox\\Twitter\\Component\\Homepage\\Controller', 'Schoolbox\\Twitter\\Component\\Dashboard\\Controller')
AND   ccd.key = 'keywords';

DELETE ccd FROM component_config_definition ccd
INNER JOIN component_definition cd ON ccd.component_definition_id = cd.id
WHERE cd.class IN ('Schoolbox\\Twitter\\Component\\Homepage\\Controller', 'Schoolbox\\Twitter\\Component\\Dashboard\\Controller')
AND   ccd.key IN ('keywords', 'username');
