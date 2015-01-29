-- Make sure all category codes for soft-deleted categories are soft-deleted too
UPDATE `categoryCode` `cc`
INNER JOIN `category` `ca` ON `cc`.`categoryID` = `ca`.`id`
    SET `cc`.`deleted_at` = `ca`.`deleted_at`
    WHERE `ca`.`deleted_at` IS NOT NULL;
