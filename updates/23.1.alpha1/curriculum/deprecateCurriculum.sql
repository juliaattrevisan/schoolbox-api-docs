/**
 * Completely deprecate this table.
 */
rename table curriculum_source to _curriculum_source;

alter table _curriculum_learning_area
    drop foreign key _curriculum_learning_area_ibfk_1;

alter table _curriculum_learning_level
    drop foreign key curriculum_year_level_ibfk_1;

