-- Student - "email" effects become "homeEmail"

UPDATE `ups_effect`
SET arguments = REPLACE(arguments, '{:email', '{:homeEmail')
WHERE `rule_id` = 1;

-- Parent & Staff - "email" effects become "defaultEmail"

UPDATE `ups_effect`
SET arguments = REPLACE(arguments, '{:email', '{:defaultEmail')
WHERE `rule_id` IN (2, 3);
