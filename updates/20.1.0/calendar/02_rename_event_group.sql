-- event_group actually represents role associations with an event

ALTER TABLE `event_group`
    RENAME TO  `event_role` ;

-- group_id is actually role_id

ALTER TABLE `event_role`
    DROP FOREIGN KEY `fk_event_group_role_id`;
ALTER TABLE `event_role`
    CHANGE COLUMN `group_id` `role_id` INT(11) UNSIGNED NOT NULL ;
ALTER TABLE `event_role`
    ADD CONSTRAINT `fk_event_group_role_id`
        FOREIGN KEY (`role_id`)
            REFERENCES `groups` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;

