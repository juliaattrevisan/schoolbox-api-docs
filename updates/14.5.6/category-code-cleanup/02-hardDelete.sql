-- Make sure all category codes for hard-deleted categories are hard-deleted too
DELETE `cc` FROM `categoryCode` `cc`
LEFT JOIN `category` `ca` ON `cc`.`categoryID` = `ca`.`id`
    WHERE `ca`.`id` IS NULL;
