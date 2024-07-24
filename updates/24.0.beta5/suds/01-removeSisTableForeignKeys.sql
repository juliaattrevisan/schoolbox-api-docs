/**
 * Strip all foreign keys from the `sis_*` tables.
 *
 * This is required because:
 * * the SUDS sync creates shadow tables using a
 *   `CREATE OR REPLACE TABLE shadow LIKE base` statement: this does not copy
 *   foreign keys from the base table, thus making the shadow tables an inexact
 *   copy of the base tables
 * * at the end of the SUDS sync, the shadow and base are swapped using a
 *   `RENAME TABLE` statement: this leaves the keys on the shadow table, but not
 *   the base
 * * the next SUDS run, when it runs `CREATE OR REPLACE TABLE shadow LIKE BASE`,
 *   fails because it tries to drop the shadow table on which another table has
 *   a FK relation
 */
alter table sis_class_user_overrides
    drop foreign key fk_sis_class_user_overrides;

alter table sis_event_campus
    drop foreign key fk_sis_event_campus;

alter table sis_event_roletype
    drop foreign key fk_sis_event_roletype;

alter table sis_event_user
    drop foreign key fk_sis_event_user_idx;

alter table sis_user_profile_parent
    drop foreign key fk1_sis_user_profile_parent;

alter table sis_user_profile_relation
    drop foreign key fk_sis_profile_relation_1;

alter table sis_user_profile_relation
    drop foreign key fk_sis_profile_relation_2;

alter table sis_user_profile_staff
    drop foreign key fk1_sis_user_profile_staff;

alter table sis_user_profile_student
    drop foreign key fk1_sis_user_profile_student;
