/**
 * Deprecate curriculum node learning area/level fields
 */
alter table curriculum_node
    change curriculum_learning_level_id _curriculum_learning_level_id int null,
    change curriculum_learning_area_id _curriculum_learning_area_id int null,
    drop index curriculum_node_ibfk_1,
    drop index curriculum_node_ibfk_3;
