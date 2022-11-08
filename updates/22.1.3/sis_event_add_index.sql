CREATE OR REPLACE INDEX `start_at_idx` ON `sis_event` (`start_at`);
CREATE OR REPLACE INDEX `event_id_idx` ON `sis_event_user` (`event_id`);
CREATE OR REPLACE INDEX `event_id_idx` ON `sis_event_campus` (`event_id`);
CREATE OR REPLACE INDEX `event_id_idx` ON `sis_event_roletype` (`event_id`);
