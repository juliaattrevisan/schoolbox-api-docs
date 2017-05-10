-- Add indefinite article for localisation strings
ALTER TABLE `localisation`
  ADD COLUMN `indefinite_article` VARCHAR(8) NOT NULL DEFAULT 'a' AFTER `capitalise_first`;

UPDATE `localisation`
SET `indefinite_article` = 'an'
WHERE substr(`singular_form`, 1, 1) IN ('a', 'e', 'i', 'o', 'u');
