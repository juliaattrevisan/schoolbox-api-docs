-- We now solely use 'site_school_code' to identify the organisation that owns this instance
DELETE FROM config WHERE name = 'site_location';

-- Not strictly necessary, as this class name is not derived dynamically, but will be confusing if we continue to use a mix.
UPDATE component_definition SET class = 'Custom\\Lc\\Survey\\Component\\Eportfolio\\Controller' WHERE class = 'Custom\\Loyola\\Survey\\Component\\Eportfolio\\Controller';
