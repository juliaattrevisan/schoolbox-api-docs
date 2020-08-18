-- Add a new Component Config Definition to all Homepage components
-- I had a look at what happens in Dashboard if I add for them; crazy crap is going on over there

-- Textboxes show titles by default
-- All components except Breadcrumbs and Tiles show titles by default

INSERT INTO
    component_config_definition (`component_definition_id`, `key`, `default_value`, `label`, `description`)
    (
        SELECT
            id, 'collapse', 0, 'Collapse Content', null
        FROM component_definition
        WHERE `module` = 'homepage'
          AND `class` IN ('Schoolbox\\LTI\\Component\\Homepage\\Controller')
    );
