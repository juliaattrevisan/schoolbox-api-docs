ALTER TABLE `curriculum_node_for_assessment`
  CHANGE COLUMN `assessment_id` `assessment_id` INT(11) UNSIGNED NOT NULL;

DELETE curriculum_node_for_assessment FROM curriculum_node_for_assessment
LEFT JOIN assessment ON assessment.id = curriculum_node_for_assessment.assessment_id
WHERE assessment.id IS NULL;

DELETE curriculum_node_for_assessment FROM curriculum_node_for_assessment
LEFT JOIN curriculum_node ON curriculum_node_for_assessment.curriculum_node_id = curriculum_node.id
WHERE curriculum_node.id IS NULL;

ALTER TABLE `curriculum_node_for_assessment`
  ADD CONSTRAINT `fk_curriculum_node_for_assessment`
FOREIGN KEY (`assessment_id`)
REFERENCES `assessment` (`id`)
  ON DELETE CASCADE
  ON UPDATE NO ACTION,
    ADD CONSTRAINT `fk_curriculum_node`
FOREIGN KEY (`curriculum_node_id`)
REFERENCES `curriculum_node` (`id`)
  ON DELETE CASCADE
  ON UPDATE NO ACTION;
