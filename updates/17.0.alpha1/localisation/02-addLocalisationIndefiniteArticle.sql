-- Add indefinite article for localisation strings
ALTER TABLE `localisation`
  ADD COLUMN `indefinite_article` VARCHAR(8) NOT NULL AFTER `capitalise_first`;

UPDATE `localisation`
SET `indefinite_article` = IF (substr(`singular_form`, 1, 1) IN ('a', 'e', 'i', 'o', 'u'), 'an', 'a');
