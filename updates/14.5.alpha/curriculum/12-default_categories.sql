-- Create some default learning areas and levels.
SELECT @customId := `id` FROM `curriculum_source` WHERE `class` = '' LIMIT 1;
INSERT INTO `curriculum_learning_level` (
  `curriculum_source_id`,
  `name`,
  `description`,
  `order`,
  `created_at`,
  `modified_at`
)
VALUES
  (@customId, 'ELC', 'Early Learning', 0, NOW(), NOW()),
  (@customId, 'Prep', 'Prep', 1, NOW(), NOW()),
  (@customId, 'Year 1', 'Year 1', 2, NOW(), NOW()),
  (@customId, 'Year 2', 'Year 2', 3, NOW(), NOW()),
  (@customId, 'Year 3', 'Year 3', 4, NOW(), NOW()),
  (@customId, 'Year 4', 'Year 4', 5, NOW(), NOW()),
  (@customId, 'Year 5', 'Year 5', 6, NOW(), NOW()),
  (@customId, 'Year 6', 'Year 6', 7, NOW(), NOW()),
  (@customId, 'Year 7', 'Year 7', 8, NOW(), NOW()),
  (@customId, 'Year 8', 'Year 8', 9, NOW(), NOW()),
  (@customId, 'Year 9', 'Year 9', 10, NOW(), NOW()),
  (@customId, 'Year 10', 'Year 10', 11, NOW(), NOW()),
  (@customId, 'Year 11', 'Year 11', 12, NOW(), NOW()),
  (@customId, 'Year 12', 'Year 12', 13, NOW(), NOW())
;

INSERT INTO `curriculum_learning_area` (
  `curriculum_source_id`,
  `name`,
  `description`,
  `order`,
  `created_at`,
  `modified_at`
)
VALUES
  (@customId, 'English', 'English', 0, NOW(), NOW()),
  (@customId, 'Mathematics', 'Mathematics', 1, NOW(), NOW()),
  (@customId, 'Science', 'Science', 2, NOW(), NOW()),
  (@customId, 'Arts', 'Arts', 3, NOW(), NOW()),
  (@customId, 'Humanities', 'Humanities', 4, NOW(), NOW())
;
