CREATE TABLE eportfolio_import
(
  id INT(11) NOT NULL AUTO_INCREMENT,
  owner_id INT,
  status VARCHAR(50) DEFAULT 'Pending',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE eportfolio_import_log
(
  id INT(11) NOT NULL AUTO_INCREMENT,
  import_id INT,
  result TEXT,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET=utf8;
