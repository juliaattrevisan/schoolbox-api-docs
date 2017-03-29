-- A list of filters available in the system
CREATE TABLE filter (
  id INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  class_file VARCHAR(255) NOT NULL
) ENGINE=INNODB;

-- A set of options available for each filter.
-- Some options may be hard-coded. The filter implementation logic determines whether to use configured options or hard-coded ones.
CREATE TABLE filter_option (
  id INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  filter_id INT(11) NOT NULL,
    INDEX filter_option_filter_id (filter_id),
    CONSTRAINT fk_filter_option_filter_id
      FOREIGN KEY (filter_id)
      REFERENCES filter(id)
      ON DELETE CASCADE ON UPDATE CASCADE,
  `key` VARCHAR(255) NOT NULL
) ENGINE=INNODB;

-- Admin configured default filter selections per role-type
CREATE TABLE filter_default (
  id INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  filter_option_id INT(11) NOT NULL,
    INDEX filter_default_filter_option_id (filter_option_id),
    CONSTRAINT fk_filter_default_filter_option_id
      FOREIGN KEY (filter_option_id)
      REFERENCES filter_option(id)
      ON DELETE CASCADE ON UPDATE CASCADE,
  role_type VARCHAR(255) NOT NULL,
  selected BOOL
) ENGINE=INNODB;

-- User configured filter selections per user
CREATE TABLE filter_selection (
  id INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  user_id INT(11) UNSIGNED NOT NULL,
    INDEX filter_selection_user_id (user_id),
    CONSTRAINT fk_filter_selection_user_id
      FOREIGN KEY (user_id)
      REFERENCES user(id)
      ON DELETE CASCADE ON UPDATE CASCADE,
  filter_option_id INT(11) NOT NULL,
    INDEX filter_selection_filter_option_id (filter_option_id),
    CONSTRAINT fk_filter_selection_filter_option_id
      FOREIGN KEY (filter_option_id)
      REFERENCES filter_option(id)
      ON DELETE CASCADE ON UPDATE CASCADE,
  selected BOOL
) ENGINE=INNODB;


