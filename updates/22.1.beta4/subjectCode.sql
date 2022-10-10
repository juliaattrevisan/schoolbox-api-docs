# we are using the subject code as a string, so just use it as a string.

update course_specification SET subject_code='' where subject_code is NULL;

alter table course_specification
    modify subject_code varchar(20) default '' not null;

