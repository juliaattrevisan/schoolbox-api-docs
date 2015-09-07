INSERT INTO `lu_config_field` (`id`, `field`) VALUES (11, 'colour');

UPDATE config SET field_id = (SELECT id FROM lu_config_field WHERE FIELD LIKE 'colour') WHERE NAME LIKE '%_colour_%';