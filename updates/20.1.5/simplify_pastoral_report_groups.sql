-- recreate pastoral_report_groups table with least structure
CREATE TABLE IF NOT EXISTS _pastoral_report_groups
SELECT * FROM pastoral_report_groups;

DROP TABLE pastoral_report_groups;

CREATE TABLE `pastoral_report_groups` (
  `report_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  UNIQUE KEY `pastoral_report_groups` (`report_id`, `group_id`),
  CONSTRAINT `fk_pastoral_report_group_1` FOREIGN KEY (`report_id`) REFERENCES `pastoral_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pastoral_report_group_2` FOREIGN KEY (`group_id`) REFERENCES `pastoral_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

INSERT INTO pastoral_report_groups
SELECT DISTINCT report_id, group_id FROM _pastoral_report_groups WHERE deleted_at IS NULL;
