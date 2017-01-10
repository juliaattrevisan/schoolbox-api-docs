-- New shared living space
CREATE TABLE `curriculum_node_for_assessment`( `curriculum_node_id` INT(11) NOT NULL, `assessment_id` INT(11) NOT NULL, UNIQUE INDEX `curriculum_node_for_assessment_unique` (`curriculum_node_id`, `assessment_id`), INDEX `curriculum_node_for_assessment_node_id` (`curriculum_node_id`), INDEX `curriculum_node_for_assessment_assessment_id` (`assessment_id`) );

-- Migrate curriculum_node_for_submission_box contents
INSERT INTO `curriculum_node_for_assessment` (`curriculum_node_id`, `assessment_id`)
SELECT
`curriculum_node_for_submission_box`.`curriculum_node_id`,
`submission_box`.`assessment_id`
FROM `submission_box`
INNER JOIN `curriculum_node_for_submission_box`
ON `submission_box`.`id` = `curriculum_node_for_submission_box`.`submission_box_id`

-- Migrate curriculum_node_for_assess contents
INSERT INTO `curriculum_node_for_assessment` (`curriculum_node_id`, `assessment_id`)
SELECT
`curriculum_node_for_assess`.`curriculum_node_id`,
`assess`.`assessment_id`
FROM `assess`
INNER JOIN `curriculum_node_for_assess`
ON `assess`.`assess_id` = `curriculum_node_for_assess`.`assess_id`

-- Brutally deprecate old tables
RENAME TABLE `curriculum_node_for_assess` TO `schoolbox`.`curriculum_node_for_assess_deprecated`;
RENAME TABLE `curriculum_node_for_submission_box` TO `schoolbox`.`curriculum_node_for_submission_box_deprecated`;
