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
(227, 'Maximum number of attendees has been reached.');

-- All attendance=1 events are now decided to be closest to Open Attendance
-- Update them accordingly

UPDATE `event`
SET attendance = 2
WHERE attendance = 1;

-- Show Attendees List

ALTER TABLE `event`
    ADD COLUMN `show_attendees` TINYINT(1) NULL DEFAULT 1 AFTER `attendance`;

-- Config option for allowing students to invite people to their events
-- First push everything else out of the way

UPDATE config SET sequence = sequence+1 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'Policy') and sequence >= 40;

-- Then shank it into place

INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
(
    'calendar_student_can_invite',
    1,
    'Allow students to invite others to events',
    "If toggled to 'ON', students will be able to create events in the calendar and invite other users in the system to these events",
    (SELECT id FROM lu_config_field where field = 'boolean'),
    null,
    (SELECT id FROM lu_config_type WHERE type = 'Policy'),
    'true',
    'false',
    'true',
    'settings',
    40
);
