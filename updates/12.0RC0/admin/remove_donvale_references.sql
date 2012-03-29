/* Removes Donvale from the title of NON SCHOOL SPECIFIC configuration options.
*/
UPDATE config
SET title = REPLACE (title,'Donvale ','');