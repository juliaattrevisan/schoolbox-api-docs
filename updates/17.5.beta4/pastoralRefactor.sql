-- Pastoral permissions unscrewing

-- Change the current body field to the `private` body

ALTER TABLE `pastoral` CHANGE `body` `body_private` TEXT NOT NULL;
ALTER TABLE `pastoral_revision` CHANGE `body` `body_private` TEXT NOT NULL;

-- Create a new blank `public` body

ALTER TABLE `pastoral` ADD `body_public` TEXT NULL;
ALTER TABLE pastoral MODIFY COLUMN `body_public` TEXT AFTER `body_private`;

ALTER TABLE `pastoral_revision` ADD `body_public` TEXT NULL;
ALTER TABLE pastoral_revision MODIFY COLUMN `body_public` TEXT AFTER `body_private`;

-- Undeprecate the `private` flag

ALTER TABLE `pastoral` CHANGE `_private` `private` TINYINT(4) DEFAULT '0' COMMENT 'reinstated in 17.0.4';

ALTER TABLE `pastoral_revision` CHANGE `_private` `private` TINYINT(4) DEFAULT '0' COMMENT 'reinstated in 17.0.4';

-- Add a `private` flag to each access record

-- If this is ticked then the user or group reference by the record will be able to see the `body_private` field

-- If this is NOT ticked they will only be able to see the `body_public` field

ALTER TABLE `pastoral_access` ADD `private` INT DEFAULT 0 NULL;

-- Put a default message into the `body_public` field for every record

UPDATE `pastoral` SET `body_public` = 'The contents of this record are private. Please contact the author for more information.';

-- We now need private and public attachments

ALTER TABLE `pastoral_attachment` ADD `private` INT DEFAULT 0 NULL;

UPDATE `pastoral_attachment` SET `private` = 1 WHERE `pastoral_id` IN (
  SELECT `id` FROM `pastoral` WHERE `private` = 1
);

-- We now need private and public comments too

ALTER TABLE `pastoral_comment` ADD `private` INT DEFAULT 0 NULL;

UPDATE `pastoral_comment` SET `private` = 1 WHERE `pastoral_id` IN (
  SELECT `id` FROM `pastoral` WHERE `private` = 1
);

ALTER TABLE `lu_pastoral_category` RENAME TO `lu_pastoral_subtype`;

ALTER TABLE `pastoral` CHANGE `category_id` `subtype_id` INT(11);
ALTER TABLE `pastoral` MODIFY COLUMN `subtype_id` INT(11) AFTER `type_id`;

ALTER TABLE `pastoral_revision` CHANGE `category_id` `subtype_id` INT(11);
ALTER TABLE `pastoral_revision` MODIFY COLUMN `subtype_id` INT(11) AFTER `type_id`;

-- Localise Confidential

INSERT INTO localisation (term, title, singular_form, plural_form, capitalise_first, indefinite_article)
VALUES ('pastoralPrivate', 'Pastoral Care (Confidential)', 'confidential', 'confidential', 1, 'a');

ALTER TABLE pastoral_access CHANGE private access INT(11) DEFAULT '0';