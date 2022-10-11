# we are using the subject code as a string, so just use it as a string.

update course_specification SET subject_code='' where subject_code is NULL;

alter table course_specification
    modify subject_code varchar(20) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' default '' not null;

