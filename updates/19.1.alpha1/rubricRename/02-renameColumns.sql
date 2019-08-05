ALTER TABLE rubric_capability_mark                CHANGE rubric_group_id      rubric_capability_id       INT(11) UNSIGNED NOT NULL;
ALTER TABLE rubric_capability_usage               CHANGE rubric_group_id      rubric_capability_id       INT(11) UNSIGNED NOT NULL;
ALTER TABLE rubric_indicator_mark                 CHANGE rubric_group_mark_id rubric_capability_mark_id  INT(11) UNSIGNED NOT NULL;
ALTER TABLE rubric_indicator                      CHANGE rubric_group_id      rubric_capability_id       INT(11) UNSIGNED NOT NULL;
ALTER TABLE rubric_descriptor                     CHANGE criterion_id         rubric_indicator_id        INT     UNSIGNED NOT NULL;
ALTER TABLE rubric_indicator_usage                CHANGE criterion_id         rubric_indicator_id        INT(11) UNSIGNED NOT NULL;
ALTER TABLE curriculum_node_for_rubric_capability CHANGE rubric_group_id      rubric_capability_id       INT(11) UNSIGNED NOT NULL;
ALTER TABLE curriculum_node_for_rubric_indicator  CHANGE rubric_criterion_id  rubric_indicator_id        INT(11) UNSIGNED NOT NULL;
ALTER TABLE submission_box                        CHANGE rubric_group_marks   rubric_mark_per_capability INT(11) UNSIGNED DEFAULT 0 NOT NULL;

# Why aren't these columns INT(11)?
ALTER TABLE rubric_descriptor     MODIFY id                  INT(11) UNSIGNED AUTO_INCREMENT;
ALTER TABLE rubric_descriptor     MODIFY rubric_indicator_id INT(11) UNSIGNED NOT NULL;
ALTER TABLE rubric_descriptor     MODIFY level_id            INT(11) UNSIGNED NOT NULL;
ALTER TABLE rubric_indicator_mark MODIFY id                  INT(11) UNSIGNED AUTO_INCREMENT;
ALTER TABLE rubric_level          MODIFY id                  INT(11) UNSIGNED AUTO_INCREMENT;
ALTER TABLE rubric_level          MODIFY box_id              INT(11) UNSIGNED NOT NULL;
