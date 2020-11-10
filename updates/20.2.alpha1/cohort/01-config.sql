
-- Each year level has this option

ALTER TABLE `lu_year_level`
    ADD COLUMN `lms_cohort_comparison` TINYINT(1) NOT NULL DEFAULT '0' AFTER `show_rubric_points`;

-- Global fallback

INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
   'lms_cohort_comparision',
   '0',
   'Enable Cohort Comparison',
   'If toggled \'ON\', the graphs in the Grades page will use marks from the entire cohort instead of just the class.',
   (SELECT id FROM lu_config_field WHERE field = 'boolean'),
   null,
   (SELECT id FROM lu_config_type WHERE `type` = 'LMS Policy'),
   'true',
   'false',
   'false',
   'settings',
   (SELECT MAX(sequence)+1 FROM config c WHERE type_id = (SELECT id FROM lu_config_type WHERE `type` = 'LMS Policy'))
)
