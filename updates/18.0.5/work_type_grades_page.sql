ALTER TABLE `lu_work_type`
ADD COLUMN `show_on_grades_page` bool;

UPDATE `lu_work_type`
SET `show_on_grades_page` = 1
WHERE `name` = "Assessment Task"
AND EXISTS (

SELECT * FROM `config`
WHERE `name` = "site_location"
AND value IN ("peninsula", "btac")

);


