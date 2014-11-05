-- Custom curriculum source - cannot be used for importing,
-- but you can place your own learning levels and areas here.
insert into `curriculum_source` (
  `name`,
  `class`,
  `description`,
  `created_at`,
  `modified_at`,
  `deleted_at`
) values (
  'Custom',
  '',
  'Customised curriculum for your school',
  NOW(),
  NOW(),
  NULL
);
