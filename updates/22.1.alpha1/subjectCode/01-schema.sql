-- Courses can now have a subject code... for api use
alter table course_specification
    add subject_code varchar(20) null;

INSERT INTO schoolbox.localisation (term, title, singular_form, plural_form, capitalise_first, indefinite_article)
VALUES ('class', 'Class', 'class', 'class', 1, 'a');
