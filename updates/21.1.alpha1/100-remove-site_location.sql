-- We now solely use 'site_school_code' to identify the organisation that owns this instance
DELETE FROM config WHERE name = 'site_location';

-- Not strictly necessary, as this class name is not derived dynamically, but will be confusing if we continue to use a mix.
UPDATE component_definition SET class = 'Custom\\Lc\\Survey\\Component\\Eportfolio\\Controller' WHERE class = 'Custom\\Loyola\\Survey\\Component\\Eportfolio\\Controller';

-- Insert disabled, if already exists, retain current enabled status
INSERT INTO component_definition (
    name,
    class,
    description,
    module,
    enabled,
    visible,
    multiple
) VALUES (
    'Student Awards',
    'Component_Dashboard_StMichaelsAwardsController',
    'Shows awards, detentions and absences for your students',
    'dashboard',
    '0',   -- Insert disabled
    'true',
    'false'
) ON DUPLICATE KEY UPDATE enabled = enabled; -- but if already exists, retain enabled state

-- Trim any wrapping whitespace so we don't need to do this in the code
UPDATE config SET value = UPPER(TRIM(value)) where name = 'site_school_code';
