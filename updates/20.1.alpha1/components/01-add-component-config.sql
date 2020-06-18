-- Add a new Component Config Definition to all Homepage components
-- I had a look at what happens in Dashboard if I add for them; crazy crap is going on over there

-- Textboxes show titles by default

INSERT INTO
component_config_definition (`component_definition_id`, `key`, `default_value`, `label`, `description`)
(
    SELECT
        id, 'showComponentTitle', 1, 'Show title to read-only users', null
    FROM component_definition
    WHERE `module` = 'homepage'
    AND `class` = 'Component_Homepage_TextBoxController'
);

-- Tiles and Breadcrumbs hide titles by default

INSERT INTO
component_config_definition (`component_definition_id`, `key`, `default_value`, `label`, `description`)
(
    SELECT
        id, 'showComponentTitle', 0, 'Show title to read-only users', null
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

-- Update old "hide heading" Homepage component configs back to their original name
-- Yes, there are two variants in legacy

UPDATE component_config_definition
SET `label` = 'Compact Mode'
WHERE `label` IN (
    'Hide heading from read only users',
    'Hide Heading from read-only users'
);
