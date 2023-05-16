-- Creation of an assessment directly within a folder
CREATE TRIGGER mv_assessment_relation_assessment_folder
    AFTER INSERT ON assessment_folder FOR EACH ROW
    INSERT INTO mv_assessment_relation (
        assessment_id,
        folder_id,
        project_assessment_id,
        course_implementation_id,
        course_unit_implementation_id,
        course_specification_id,
        course_unit_specification_id
    )
    SELECT
        NEW.assessment_id,
        NEW.folder_id,
        pa.project_id,
        NULL,
        NULL,
        NULL,
        NULL
    FROM NEW
    LEFT JOIN project_assessment pa ON NEW.assessment_id = pa.assessment_id;

-- Creation of an assessment in a course implementation
CREATE TRIGGER mv_assessment_relation_assessment_course_unit_implementation
    AFTER INSERT ON assessment_course_unit_implementation FOR EACH ROW
    INSERT INTO mv_assessment_relation (
        assessment_id,
        folder_id,
        project_assessment_id,
        course_implementation_id,
        course_unit_implementation_id,
        course_specification_id,
        course_unit_specification_id
    )
    SELECT
        NEW.assessment_id,
        ci.folder_id,
        pa.project_id,
        ci.id,
        NEW.course_unit_implementation_id,
        NULL,
        NULL
    FROM NEW
    LEFT JOIN project_assessment pa ON NEW.assessment_id = pa.assessment_id
    JOIN course_unit_implementation cui ON NEW.course_unit_implementation_id = cui.id
    JOIN course_implementation ci ON cui.course_implementation_id = ci.id;

-- Creation of an assessment in a course specification
CREATE TRIGGER mv_assessment_relation_assessment_course_unit_specification
    AFTER INSERT ON assessment_course_unit_specification FOR EACH ROW
    INSERT INTO mv_assessment_relation (
        assessment_id,
        folder_id,
        project_assessment_id,
        course_implementation_id,
        course_unit_implementation_id,
        course_specification_id,
        course_unit_specification_id
    )
    SELECT
        NEW.assessment_id,
        cs.folder_id,
        pa.project_id,
        NULL,
        NULL,
        cs.id,
        NEW.course_unit_specification_id
    FROM NEW
    LEFT JOIN project_assessment pa ON NEW.assessment_id = pa.assessment_id
    JOIN course_unit_specification cus on NEW.course_unit_specification_id = cus.id
    JOIN course_specification cs on cus.course_specification_id = cs.id;
