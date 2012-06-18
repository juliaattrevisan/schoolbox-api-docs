/* Hide google translate homepage component
 */
UPDATE homepage_comp SET invisible = 'true' WHERE method = 'outputTranslate';

/* Remove this component entirely from all homepages
 */
DELETE FROM homepage_comp_location WHERE component IN (SELECT id FROM homepage_comp WHERE method = 'outputTranslate');