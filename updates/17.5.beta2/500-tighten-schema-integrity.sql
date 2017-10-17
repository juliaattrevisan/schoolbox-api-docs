-- The relationship from ReturnedWorkFile to Storage is mandatory, not optional
DELETE FROM submission_return_file WHERE hash IS NULL;

ALTER TABLE submission_return_file
    DROP FOREIGN KEY fk_submission_return_file_hash;

ALTER TABLE submission_return_file
    CHANGE COLUMN hash hash VARCHAR(100) NOT NULL COLLATE ascii_general_ci;

ALTER TABLE submission_return_file
    ADD CONSTRAINT fk_submission_return_file_hash FOREIGN KEY (hash)
      REFERENCES `storage`(`hash`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;
