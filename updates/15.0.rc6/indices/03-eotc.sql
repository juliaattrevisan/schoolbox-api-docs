-- Various EOTC indices
ALTER TABLE `eotc_activitylog`
    ADD INDEX `actioner_id` (`actioner_id`),
    ADD INDEX `metadata_id` (`metadata_id`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
ALTER TABLE `eotc_event`
    ADD INDEX `series_id` (`series_id`),
    ADD INDEX `author_id` (`author_id`),
    ADD INDEX `teacher_in_charge_id` (`teacher_in_charge_id`)
;
ALTER TABLE `eotc_event_campus`
    ADD INDEX `event_id` (`event_id`),
    ADD INDEX `campus_id` (`campus_id`)
;
ALTER TABLE `eotc_event_metadata`
    ADD INDEX `event_id` (`event_id`),
    ADD INDEX `metadata_id` (`metadata_id`)
;
ALTER TABLE `eotc_metadata`
    ADD INDEX `event_type_id` (`event_type_id`),
    ADD INDEX `status_id` (`status_id`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
ALTER TABLE `eotc_metadata_file`
    ADD INDEX `metadata_id` (`metadata_id`),
    ADD INDEX `storage_id` (`storage_id`)
;
ALTER TABLE `eotc_metadata_resourcebooking`
    ADD INDEX `metadata_id` (`metadata_id`),
    ADD INDEX `booking_id` (`booking_id`)
;
ALTER TABLE `eotc_metadata_staff`
    ADD INDEX `metadata_id` (`metadata_id`),
    ADD INDEX `user_id` (`user_id`)
;
ALTER TABLE `eotc_metadata_student`
    ADD INDEX `metadata_id` (`metadata_id`),
    ADD INDEX `user_id` (`user_id`)
;
ALTER TABLE `eotc_permission`
    ADD INDEX `user_id` (`user_id`),
    ADD INDEX `campus_id` (`campus_id`),
    ADD INDEX `status_id` (`status_id`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
