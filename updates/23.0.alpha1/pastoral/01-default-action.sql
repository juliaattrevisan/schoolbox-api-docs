ALTER TABLE `pastoral_type_action`
    ADD COLUMN `id`      int(11)    NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,
    ADD COLUMN `default` tinyint(1) NOT NULL DEFAULT 0 AFTER `action_id`;
