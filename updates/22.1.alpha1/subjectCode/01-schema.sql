-- Courses can now have a subject code... for api use
alter table course_specification
    add subject_code varchar(20) null;

