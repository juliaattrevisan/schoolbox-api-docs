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
  'display_grade_graph',
  (IF ((SELECT COUNT(*)
        FROM `config` `c`
        WHERE `c`.`name` = 'site_location'
              AND `c`.`value` IN ('mgs', 'mgs-staff', 'mgs-senior', 'grimwade', 'wadhurst')),
       '0', '1')),
  'Display Grade Graph',
  'Should the Grades graph be displayed in All Work view?',
  3,
  NULL,
  5,
  'true',
  'false',
  'false'
);
