/* Hide/show criteria section on grades page
 */
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`)
SELECT
	'enable_grades_criteria', '1', 'Display Grades Criteria', 'If set, the Criteria section will be displayed in a student\'s Grades page', '3', id, 'true'
FROM lu_config_type
WHERE `type` = 'LMS Policy'
