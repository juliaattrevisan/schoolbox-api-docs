/* Hide/show criteria section on grades pageINSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`) VALUES ('enable_grades_criteria', '1', 'Enable Grades Criteria', 'If set, the Criteria section will be displayed in a student\'s Grades page', '3', 'true:false', '15', 'true');
 */
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`)
SELECT
	'enable_grades_criteria', '1', 'Display Grades Criteria', 'If set, the Criteria section will be displayed in a student\'s Grades page', '3', 'true:false', id, 'true'
FROM lu_config_type
WHERE `type` = 'LMS Policy'