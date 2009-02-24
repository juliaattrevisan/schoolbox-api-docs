UPDATE homepage_comp_location SET component = 2 WHERE component = 6;
UPDATE homepage_comp_location SET component = 3 WHERE component = 7;
UPDATE homepage_comp_location SET component = 16 WHERE component = 11;
UPDATE homepage_comp_location SET component = 17 WHERE component = 12;
UPDATE homepage_comp_location SET component = 21 WHERE component = 22;
UPDATE homepage_comp_location SET component = 26 WHERE component = 27;
UPDATE homepage_comp_location SET component = 28 WHERE component = 29;

UPDATE homepage_comp SET invisible='true' WHERE id IN(6,7,11,12,22,27,29);