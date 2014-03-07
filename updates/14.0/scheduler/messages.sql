CREATE TABLE `msg_namespace`(
  `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`  VARCHAR(32) NOT NULL,
  `label` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX (`slug`)
) CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `msg_source`(
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `namespace`     INT NOT NULL,
  `key`           VARCHAR(64) NOT NULL,
  `role_type`     ENUM('student','parent','staff') NOT NULL,
  `description`   VARCHAR(255) NOT NULL,
  `routing_key`   VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX (`namespace`, `key`, `role_type`)
) CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `msg_reroute`(
  `relation_id`    INT UNSIGNED NOT NULL,
  `relation_type`  ENUM('user','role','campus') NOT NULL,
  `routing_key`    VARCHAR(255) NOT NULL,
  `incoming_level` INT UNSIGNED NOT NULL,
  `outgoing_level` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`relation_id`, `relation_type`, `routing_key`, `incoming_level`)
) CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `msg_route_perm`(
  `msg_source_id` INT UNSIGNED NOT NULL,
  `role_id`       INT UNSIGNED NOT NULL,
  `min_level`     INT UNSIGNED NOT NULL,
  `max_level`     INT UNSIGNED NOT NULL,
  `default_level` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`msg_source_id`, `role_id`)
) CHARSET=utf8 COLLATE=utf8_bin;
