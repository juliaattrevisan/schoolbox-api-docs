-- Add a new Component Config Definition to all Homepage components
-- I had a look at what happens in Dashboard if I add for them; crazy crap is going on over there

-- Textboxes show titles by default

INSERT INTO
component_config_definition (`component_definition_id`, `key`, `default_value`, `label`, `description`)
(
    SELECT
        id, 'showComponentTitle', 1, 'Show Title to Read-Only Users', null
    FROM component_definition
    WHERE `module` = 'homepage'
    AND `class` = 'Component_Homepage_TextBoxController'
);

-- Tiles and Breadcrumbs hide titles by default

INSERT INTO
component_config_definition (`component_definition_id`, `key`, `default_value`, `label`, `description`)
(
    SELECT
        id, 'showComponentTitle', 0, 'Show Title to Read-Only Users', null
    FROM component_definition
    WHERE `module` = 'homepage'
    AND `class` IN
    (
        'Component_Homepage_BreadcrumbController',
        'Schoolbox\\Tile\\Component\\HomepageTileController'
    )
);


-- Invert special usages of CompactMode in particular components:
-- TextBox, Tiles, Breadcrumbs

-- Have a look at compactMode usage vs showComponentTitle usage, side by side

-- SELECT cci1.*, '', cci2.*
-- FROM component_config_instance cci1
-- INNER JOIN component_config_definition ccd1 ON cci1.component_config_definition_id = ccd1.id AND ccd1.`key` = 'showComponentTitle'
-- INNER JOIN component_definition cd1 ON ccd1.component_definition_id = cd1.id AND cd1.`class` IN (
--     'Component_Homepage_TextBoxController',
--     'Schoolbox\\Tile\\Component\\HomepageTileController',
--     'Component_Homepage_BreadcrumbController'
-- )
-- INNER JOIN component_config_instance cci2 ON cci1.component_instance_id = cci2.component_instance_id
-- INNER JOIN component_config_definition ccd2 ON cci2.component_config_definition_id = ccd2.id AND ccd2.`key` = 'compactMode'
-- INNER JOIN component_definition cd2 ON ccd2.component_definition_id = cd2.id AND cd2.`class` IN (
--     'Component_Homepage_TextBoxController',
--     'Schoolbox\\Tile\\Component\\HomepageTileController',
--     'Component_Homepage_BreadcrumbController'
-- )

-- Perform actual inversion

UPDATE component_config_instance cci1
INNER JOIN component_config_definition ccd1 ON cci1.component_config_definition_id = ccd1.id
    AND ccd1.`key` = 'showComponentTitle'
INNER JOIN component_definition cd1 ON ccd1.component_definition_id = cd1.id AND cd1.`class` IN (
    'Component_Homepage_TextBoxController',
    'Schoolbox\\Tile\\Component\\HomepageTileController',
    'Component_Homepage_BreadcrumbController'
)
INNER JOIN component_config_instance cci2 ON cci1.component_instance_id = cci2.component_instance_id
INNER JOIN component_config_definition ccd2 ON cci2.component_config_definition_id = ccd2.id
    AND ccd2.`key` = 'compactMode'
INNER JOIN component_definition cd2 ON ccd2.component_definition_id = cd2.id AND cd2.`class` IN (
    'Component_Homepage_TextBoxController',
    'Schoolbox\\Tile\\Component\\HomepageTileController',
    'Component_Homepage_BreadcrumbController'
)
SET cci1.`value` = IF(cci2.`value` = '1', '0', '1');

-- Old "Hide Heading" homepage component configs no longer have any behaviours in components
-- therefore they can be removed

-- Remove the compactMode instances from textbox/breadcrumb/tiles

DELETE cci
FROM component_config_instance cci
LEFT JOIN component_config_definition ccd ON ccd.id = cci.component_config_definition_id
WHERE ccd.`key` = 'compactMode'
  AND ccd.component_definition_id IN (
    SELECT id
    FROM component_definition
    WHERE `class` IN (
        'Component_Homepage_TextBoxController',
        'Component_Homepage_BreadcrumbController',
        'Schoolbox\\Tile\\Component\\HomepageTileController'
    )
);

-- Remove the compactMode definitions from textbox/breadcrumb/tiles

DELETE ccd
FROM component_config_definition ccd
LEFT JOIN component_definition cd ON ccd.component_definition_id = cd.id
WHERE ccd.`key` = 'compactMode'
  AND cd.`class` IN (
    'Component_Homepage_TextBoxController',
    'Component_Homepage_BreadcrumbController',
    'Schoolbox\\Tile\\Component\\HomepageTileController'
);


-- Find all the components who don't have usages of componentname

-- SELECT cd.*
-- FROM component_definition cd
-- LEFT JOIN component_config_definition ccd ON cd.id = ccd.component_definition_id AND `key` = 'componentname'
-- WHERE module = 'homepage'
-- AND ccd.id IS NULL

-- Assign a componentname config definition to components who don't already have it

INSERT INTO
component_config_definition (`component_definition_id`, `key`, `default_value`, `label`, `description`)
(
    SELECT cd.id, 'componentname', `name`, 'Heading', null
    FROM component_definition cd
    LEFT JOIN component_config_definition ccd ON cd.id = ccd.component_definition_id AND `key` = 'componentname'
    WHERE module = 'homepage'
    AND ccd.id IS NULL
);
