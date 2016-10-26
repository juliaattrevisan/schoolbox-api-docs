INSERT INTO `component_definition`
(
  `name`,
  `class`,
  `description`,
  `module`,
  `component_category`,
  `visible`,
  `multiple`
)
VALUES
(
  'My Classes',
  'Schoolbox\\Learning\\Component\\Dashboard\\ClassController',
  'This will show all the classes a user is enrolled in as tiles with quicklinks to useful pages',
  'dashboard',
  1,
  'true',
  'false'
);