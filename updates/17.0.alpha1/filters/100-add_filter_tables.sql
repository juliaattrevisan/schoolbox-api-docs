-- TODO remove this debug aids
DROP TABLE IF EXISTS filter_default;
DROP TABLE IF EXISTS filter_selection;
DROP TABLE IF EXISTS filter_option;
DROP TABLE IF EXISTS filter;

-- A list of filters available in the system
CREATE TABLE filter (
  `id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `class` VARCHAR(255) NOT NULL
) ENGINE=INNODB;

-- A set of options available for each filter.
-- Some options may be hard-coded. The filter implementation logic determines whether to use configured options or hard-coded ones.
CREATE TABLE filter_option (
  `id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `filter_id` INT(11) NOT NULL,
    INDEX filter_option_filter_id (`filter_id`),
    CONSTRAINT fk_filter_option_filter_id
      FOREIGN KEY (`filter_id`)
      REFERENCES filter(`id`)
      ON DELETE CASCADE ON UPDATE CASCADE,
  `page` ENUM ('pending','grades','markbook','analytics','dueWorkCal') NOT NULL,
  `key` VARCHAR(255) NOT NULL COMMENT 'An abstract key - eg \'all\', \'future\', etc',
  `available` BOOL NOT NULL
) ENGINE=INNODB;



-- Admin configured default filter selections per role-type
CREATE TABLE filter_default (
  `id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `filter_option_id` INT(11) NOT NULL,
    INDEX filter_default_filter_option_id (`filter_option_id`),
    CONSTRAINT fk_filter_default_filter_option_id
      FOREIGN KEY (`filter_option_id`)
      REFERENCES filter_option(`id`)
      ON DELETE CASCADE ON UPDATE CASCADE,
  `role_type` VARCHAR(255) NOT NULL,
  `selected` BOOL
) ENGINE=INNODB;

-- User configured filter selections per user
-- Note that this table is not normalised with filter_option. This is because we need to be able to save a user's settings before
-- the admin has configured the availabilities and defaults.
CREATE TABLE filter_selection (
  `filter_id` INT(11) NOT NULL,
    INDEX filter_selection_filter_id (`filter_id`),
    CONSTRAINT fk_filter_selection_filter_id
      FOREIGN KEY (`filter_id`)
      REFERENCES filter(`id`)
      ON DELETE CASCADE ON UPDATE CASCADE,
  `user_id` INT(11) UNSIGNED NOT NULL,
    INDEX filter_selection_user_id (`user_id`),
    CONSTRAINT fk_filter_selection_user_id
      FOREIGN KEY (`user_id`)
      REFERENCES user(`id`)
      ON DELETE CASCADE ON UPDATE CASCADE,
  `page` ENUM ('pending','grades','markbook','analytics','dueWorkCal') NOT NULL,
  `key` VARCHAR(255) NOT NULL COMMENT 'A concrete key - eg a Model ID',
  `selected` BOOL
) ENGINE=INNODB;

-- We don't need this anymore - it's superseded by the 'AssessmentType' filter
DELETE FROM config WHERE `name` = 'pending_assessment_types';

-- Add Filters
INSERT INTO filter (`class`) VALUES
  ('Schoolbox\\Filter\\Filters\\Assessment\\Term\\Definition'),
  ('Schoolbox\\Filter\\Filters\\Assessment\\AssessmentType\\Definition'),
  ('Schoolbox\\Filter\\Filters\\Assessment\\WorkType\\Definition'),
  ('Schoolbox\\Filter\\Filters\\Assessment\\Folder\\Definition'),
  ('Schoolbox\\Filter\\Filters\\Assessment\\WeightingType\\Definition'),
  ('Schoolbox\\Filter\\Filters\\Assessment\\UnitImportSpec\\Definition'),
  ('Schoolbox\\Filter\\Filters\\Assessment\\Project\\Definition'),
  ('Schoolbox\\Filter\\Filters\\Assessment\\UnitSpec\\Definition'),
  ('Schoolbox\\Filter\\Filters\\Assessment\\UnitImpl\\Definition')
;


