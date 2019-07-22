RENAME TABLE rubric_criterion_usage               TO rubric_indicator_usage;
RENAME TABLE rubric_criterion                     TO rubric_indicator;
RENAME TABLE rubric_descriptor_mark               TO rubric_indicator_mark;
RENAME TABLE rubric_group                         TO rubric_capability;
RENAME TABLE rubric_group_usage                   TO rubric_capability_usage;
RENAME TABLE rubric_group_mark                    TO rubric_capability_mark;
RENAME TABLE curriculum_node_for_rubric_group     TO curriculum_node_for_rubric_capability;
RENAME TABLE curriculum_node_for_rubric_criterion TO curriculum_node_for_rubric_indicator;
