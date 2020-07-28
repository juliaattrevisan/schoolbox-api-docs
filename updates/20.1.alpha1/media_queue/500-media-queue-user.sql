ALTER TABLE video_queue
    ADD COLUMN uploaded_by INT UNSIGNED NULL COMMENT 'The user who uploaded this media, if known' AFTER type,
    ADD CONSTRAINT `fk_video_queue_uploaded_by` FOREIGN KEY (uploaded_by) REFERENCES user(id);
