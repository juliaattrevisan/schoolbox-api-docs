CREATE TABLE `pastoral_report_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_report_id` int(11) NOT NULL,
  `user_id` int(11) unsigned,
  `group_id` int(11),
  `access` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral_report_access_pastoral_report_id` (`pastoral_report_id`),
  KEY `fk_pastoral_report_access_user_id_idx` (`user_id`),
  KEY `fk_pastoral_report_access_group_id_idx` (`group_id`),
  CONSTRAINT `fk_pastoral_report_access` FOREIGN KEY (`pastoral_report_id`) REFERENCES `pastoral_report` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_report_access_group_id` FOREIGN KEY (`group_id`) REFERENCES `pastoral_group` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_report_access_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
);

INSERT INTO `pastoral_report_access` (`pastoral_report_id`, `group_id`, `access`)
SELECT `report_id`, `group_id`, 0 FROM `pastoral_report_groups`;

RENAME TABLE `pastoral_report_groups` TO `_pastoral_report_groups`;
