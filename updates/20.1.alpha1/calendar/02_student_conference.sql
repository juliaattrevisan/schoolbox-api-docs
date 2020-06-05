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

-- remove mysterious column whose purpose was lost in time

ALTER TABLE `event_attendance`
    DROP COLUMN `intention`;

-- Remove duplicate attendance data

DELETE ea1 FROM event_attendance ea1
INNER JOIN event_attendance ea2 ON ea1.id < ea2.id
    AND ea1.event_id = ea2.event_id
    AND ea1.user_id = ea2.user_id;

-- Attendees can only be invited to an event once (can't have multiple identical attendance records)

ALTER TABLE `event_attendance`
    ADD UNIQUE INDEX `event_attendance_event_user` (`event_id`, `user_id`);

-- Add max_attendees; null = no limit (which is synonymous with attendance = 1, no migration needed)
-- Reorganise data a bit

ALTER TABLE `event`
    ADD COLUMN `max_attendees` INT(11) NULL AFTER `location`,
    CHANGE COLUMN `allday` `allday` TINYINT(1) NULL DEFAULT '0' AFTER `end_datetime`,
    CHANGE COLUMN `completed` `completed` TINYINT(4) NOT NULL DEFAULT '0' COMMENT 'For marking Tasks as complete or not.' AFTER `attendance`;

-- Special error message when you reach the max attendees

INSERT INTO `error` (id, message) VALUES
(227, 'Maximum number of attendees has been reached.')
