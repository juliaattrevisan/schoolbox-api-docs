-- Australian Curriculum source
insert into `curriculum_source` (
  `name`,
  `class`,
  `description`,
  `created_at`,
  `modified_at`,
  `deleted_at`
) values (
  'Australian Curriculum',
  '\\Schoolbox\\Curriculum\\Source\\AustralianCurriculum',
  'Australian Curriculum, available at http://www.australiancurriculum.edu.au/',
  NOW(),
  NOW(),
  NULL
);
