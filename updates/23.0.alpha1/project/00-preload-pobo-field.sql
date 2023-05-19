-- add pobo column in the vicinity of its date

ALTER TABLE `submission_return`
    ADD COLUMN `published_by` INT(10) UNSIGNED NOT NULL AFTER `created_at`;

-- Presume the publisher is currently the same as the original marker
-- you need this before you can FK this field

UPDATE submission_return
SET `published_by` = `marker`;

-- Foreigner key that stuff
ALTER TABLE `submission_return`
    ADD CONSTRAINT `fk_submission_return_publisher`
        FOREIGN KEY (`published_by`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;
