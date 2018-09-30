-- Component: Dashboard
UPDATE `component_definition` SET `class`='Schoolbox\\Comms\\News\\Component\\Dashboard\\Controller'
WHERE `class` = 'Schoolbox\\News\\Component\\Dashboard\\Controller';

-- Component: Homepage
UPDATE `component_definition` SET `class`='Schoolbox\\Comms\\News\\Component\\Homepage\\Controller'
WHERE `class` = 'Schoolbox\\News\\Component\\Homepage\\Controller';

-- If we're gonna INNER JOIN on this, it can't be nullable
-- Temporarily disable FK checks so we don't have to remove and re-add FK.
SET FOREIGN_KEY_CHECKS=0;
ALTER TABLE config
    CHANGE COLUMN field_id field_id INT(11) NOT NULL;
SET FOREIGN_KEY_CHECKS=1;
