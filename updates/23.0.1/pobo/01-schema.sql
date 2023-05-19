/**
 * Instances not yet updated to 23.0.0 at the time of 23.0.1 release won't yet
 * have the `submission_return.published_by` column: add it only if it does
 * not exist.
 */

-- add pobo column in the vicinity of its date, if it doesn't already exist

ALTER TABLE `submission_return`
    ADD COLUMN IF NOT EXISTS `published_by` INT(10) UNSIGNED NOT NULL AFTER `created_at`;

-- Presume the publisher is currently the same as the original marker
-- you need this before you can FK this field:
-- it doesn't matter if the column existed or not, because nothing will have
-- populated this column yet.

UPDATE submission_return
SET `published_by` = `marker`;

-- Foreigner key that stuff, if the foreign key doesn't already exist
ALTER TABLE `submission_return`
    ADD CONSTRAINT `fk_submission_return_publisher`
        FOREIGN KEY IF NOT EXISTS (`published_by`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;
