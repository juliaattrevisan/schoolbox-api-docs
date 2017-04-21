-- Fix project open dates
UPDATE `assessment` `a`
  INNER JOIN `project` `p` ON `a`.`id` = `p`.`assessment_id`
SET `a`.`open_date` = `a`.`publish_date`
WHERE `a`.`publish_date` IS NOT NULL;

-- Fix project close dates
UPDATE `assessment` `a`
  INNER JOIN `project` `p` ON `a`.`id` = `p`.`assessment_id`
SET `a`.`close_date` = `a`.`due_date`
WHERE `a`.`due_date` IS NOT NULL;
