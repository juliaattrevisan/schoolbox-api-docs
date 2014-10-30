-- New config field type: select lookup
INSERT INTO `lu_config_field` (`id`, `field`) VALUES (10, 'selectLookup') ;

-- Options for that type:
-- serialized PHP array, which can contain:
-- mapper:         a class name
-- findMethod:     a find method to call on the mapper, defaults to 'findAll'
-- getKeyMethod:   a method for getting IDs from the mapper, defaults to 'getId'
-- getValueMethod: a method for getting values from the mapper, defaults to 'getName'
INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field_id`,
  `options`,
  `type_id`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'default_grade_group',
  (SELECT `id` FROM `submission_grade_group` WHERE `name` = 'Standard' LIMIT 1),
  'Default Letter Grade Group',
  'The default letter grade group that is selected for new due work items.
  This group will also be used to determine the letter grade average,
  if there are no due work items that have a different group.',
  10, -- selectLookup
  'a:1:{s:6:"mapper";s:69:"\\Schoolbox\\Assessment\\Component\\SubmissionBox\\GradeLevel\\Group\\Mapper";}',
  15, -- LMS Policy
  'true',
  'false',
  'false'
);
