-- Update component names in the component palettes
UPDATE component_definition SET name = 'Text Box'
WHERE class IN ('Component_Dashboard_TextboxController','Component_Eportfolio_TextboxController');

-- Rename component config in homepages
UPDATE component_config_definition SET label = 'Text Box' where `key` = 'textbox';

-- Rename description in db
-- these show up when you hover over items in the component palettes
-- but sometimes the tooltip doesn't appear in your browser if you try
-- to spawn in in a particular way
UPDATE component_definition
SET description = 'Module for displaying custom content on dashboards by being linked to a pre-existing text box.'
WHERE class IN ('Component_Dashboard_TextboxController');

-- This appears in eportfolio for Eden Reed, "General - Bog (5)"
-- There's a random Text Box component that you can spawn
-- This is its config
UPDATE component_definition
SET description = 'A text box component'
WHERE class IN ('Component_Eportfolio_TextboxController');

-- Update component config names
-- This appears in Dashboard layout where you can chooze what textbox to display
UPDATE component_config_definition
SET label = 'Text Box Search'
WHERE label = 'Textbox Search' AND `key` = 'textboxSearch';

-- This appears in eportfolio for Eden Reed, "General - Bog (5)"
-- There's a random Text Box component that you can spawn
-- This is its config
UPDATE component_config_definition
SET label = 'Text Box Title'
WHERE label = 'Textbox Title' AND `key` = 'componentname';

-- Default textbox name for the eportfolio Text Box component
UPDATE component_config_definition
SET default_value = 'My Text Box'
WHERE default_value = 'My Textbox' AND `key` = 'componentname'