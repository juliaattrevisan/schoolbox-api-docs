-- Creation of an assessment directly within a folder
CREATE OR REPLACE TRIGGER mv_assessment_relation_assessment_folder
    AFTER INSERT ON assessment_folder FOR EACH ROW
    INSERT INTO mv_assessment_relation (
        assessment_id,
        folder_id
    ) VALUES (
        NEW.assessment_id,
        NEW.folder_id
    );

-- Creation of an assessment in a course implementation
CREATE OR REPLACE TRIGGER mv_assessment_relation_assessment_course_unit_implementation
    AFTER INSERT ON assessment_course_unit_implementation FOR EACH ROW
    INSERT INTO mv_assessment_relation (
        assessment_id,
        folder_id,
        course_implementation_id,
        course_unit_implementation_id
    )
    SELECT
        NEW.assessment_id,
        ci.folder_id,
        ci.id,
        NEW.course_unit_implementation_id
    FROM course_unit_implementation cui
    JOIN course_implementation ci ON cui.course_implementation_id = ci.id
    WHERE NEW.course_unit_implementation_id = cui.id;

-- Creation of an assessment in a course specification
CREATE OR REPLACE TRIGGER mv_assessment_relation_assessment_course_unit_specification
    AFTER INSERT ON assessment_course_unit_specification FOR EACH ROW
    INSERT INTO mv_assessment_relation (
        assessment_id,
        folder_id,
        course_specification_id,
        course_unit_specification_id
    )
    SELECT
        NEW.assessment_id,
        cs.folder_id,
        cs.id,
        NEW.course_unit_specification_id
    FROM course_unit_specification cus
    JOIN course_specification cs ON cus.course_specification_id = cs.id
    WHERE NEW.course_unit_specification_id = cus.id;

-- Creation of an assessment in a project which is directly within a folder
CREATE OR REPLACE TRIGGER mv_assessment_relation_project_folder
    AFTER INSERT ON project_assessment FOR EACH ROW
    INSERT INTO mv_assessment_relation (
        assessment_id,
        folder_id,
        project_assessment_id
    )
    SELECT
        NEW.assessment_id,
        af.folder_id,
        NEW.project_id
    FROM project p
    JOIN assessment_folder af ON p.assessment_id = af.assessment_id
    WHERE NEW.project_id = p.id;

-- Creation of an assessment in a project which is in a course implementation
CREATE OR REPLACE TRIGGER mv_assessment_relation_project_course_unit_implementation
    AFTER INSERT ON project_assessment FOR EACH ROW
    INSERT INTO mv_assessment_relation (
        assessment_id,
        folder_id,
        project_assessment_id,
        course_implementation_id,
        course_unit_implementation_id
    )
    SELECT
        NEW.assessment_id,
        ci.folder_id,
        NEW.project_id,
        ci.id,
        cui.id
    FROM project p
    JOIN assessment_course_unit_implementation acui ON p.assessment_id = acui.assessment_id
    JOIN course_unit_implementation cui ON acui.course_unit_implementation_id = cui.id
    JOIN course_implementation ci ON cui.course_implementation_id = ci.id
    WHERE NEW.project_id = p.id;

-- Creation of an assessment in a project which is in a course specification
CREATE OR REPLACE TRIGGER mv_assessment_relation_project_course_unit_specification
    AFTER INSERT ON project_assessment FOR EACH ROW
    INSERT INTO mv_assessment_relation (
        assessment_id,
        folder_id,
        project_assessment_id,
        course_specification_id,
        course_unit_specification_id
    )
    SELECT
        NEW.assessment_id,
        cs.folder_id,
        NEW.project_id,
        cs.id,
        cus.id
    FROM project p
    JOIN assessment_course_unit_specification acus ON p.assessment_id = acus.assessment_id
    JOIN course_unit_specification cus ON acus.course_unit_specification_id = cus.id
    JOIN course_specification cs ON cus.course_specification_id = cs.id
    WHERE NEW.project_id = p.id;
