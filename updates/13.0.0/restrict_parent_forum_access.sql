INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field`,
  `options`,
  `type`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'restrict_parent_forum_access',
  '1',
  'Restrict Parent Access to Forums',
  'Prevents parents from reading forums of classes and pages they are not directly involved in as a group member. If this is off, they may read (but not post to) forums for pages that a child is involved in.',
  'boolean',
  'true:false',
  'Policy',
  'true',
  'false',
  'false'
);
