CREATE OR REPLACE INDEX `start_at_idx` ON `sis_event` (`start_at`);
ALTER TABLE `sis_event_user`
    ADD CONSTRAINT `fk_sis_event_user_idx`
        FOREIGN KEY (`event_id`)
            REFERENCES `sis_event` (`id`)
            ON DELETE NO ACTION ON UPDATE NO ACTION;
