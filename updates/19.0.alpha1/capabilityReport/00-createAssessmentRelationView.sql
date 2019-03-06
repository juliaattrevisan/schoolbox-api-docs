/**
 * Create a view for retrieving an assessment's relation to:
 * * its folder
 * * its course spec, if any
 * * its course implementation, if any
 * * the project it's contained within, if any
 *
 * Note this cares nothing about whether an assessment, folder or anything is
 * soft-deleted.
 */
CREATE OR REPLACE VIEW v_assessment_relation AS


-- 1: Assessments that live directly within a folder
SELECT
    a.id AS assessment_id,
    f.id AS folder_id,
    NULL AS project_assessment_id,
    NULL AS course_implementation_id,
    NULL AS course_unit_implementation_id,
    NULL AS course_specification_id,
    NULL AS course_unit_specification_id
FROM       assessment        a
INNER JOIN assessment_folder af ON a.id         = af.assessment_id
INNER JOIN folder            f  ON af.folder_id = f.id


-- 2: Assessments that live within a course implementation
-- TODO consider including course/unit spec here as well
UNION
SELECT
    a.id   AS assessment_id,
    f.id   AS folder_id,
    NULL   AS project_assessment_id,
    ci.id  AS course_implementation_id,
    cui.id AS course_unit_implementation_id,
    NULL   AS course_specification_id,
    NULL   AS course_unit_specification_id
FROM       assessment                            a
INNER JOIN assessment_course_unit_implementation acui ON a.id                               = acui.assessment_id
INNER JOIN course_unit_implementation            cui  ON acui.course_unit_implementation_id = cui.id
INNER JOIN course_implementation                 ci   ON cui.course_implementation_id       = ci.id
INNER JOIN folder                                f    ON ci.folder_id                       = f.id



-- 3: Assessments that live within a course specification
UNION
SELECT
    a.id   AS assessment_id,
    f.id   AS folder_id,
    NULL   AS project_assessment_id,
    NULL   AS course_implementation_id,
    NULL   AS course_unit_implementation_id,
    cs.id  AS course_specification_id,
    cus.id AS course_unit_specification_id
FROM       assessment                           a
INNER JOIN assessment_course_unit_specification acus ON a.id                              = acus.assessment_id
INNER JOIN course_unit_specification            cus  ON acus.course_unit_specification_id = cus.id
INNER JOIN course_specification                 cs   ON cus.course_specification_id       = cs.id
INNER JOIN folder                               f    ON cs.folder_id                      = f.id


-- 4: Assessments that live within a project, which lives directly within a folder
UNION
SELECT
    a.id AS assessment_id,
    f.id AS folder_id,
    p.id AS project_assessment_id,
    NULL AS course_implementation_id,
    NULL AS course_unit_implementation_id,
    NULL AS course_specification_id,
    NULL AS course_unit_specification_id
FROM       assessment         a
INNER JOIN project_assessment pa ON a.id            = pa.assessment_id
INNER JOIN project            p  ON pa.project_id   = p.id
INNER JOIN assessment_folder  af ON p.assessment_id = af.assessment_id
INNER JOIN folder             f  ON af.folder_id    = f.id


-- 5: Assessments that live within a project, which lives within a course implementation
-- TODO consider including course/unit spec here as well
UNION
SELECT
    a.id   AS assessment_id,
    f.id   AS folder_id,
    p.id   AS project_assessment_id,
    ci.id  AS course_implementation_id,
    cui.id AS course_unit_implementation_id,
    NULL   AS course_specification_id,
    NULL   AS course_unit_specification_id
FROM       assessment                            a
INNER JOIN project_assessment                    pa   ON a.id                               = pa.assessment_id
INNER JOIN project                               p    ON pa.project_id                      = p.id
INNER JOIN assessment_course_unit_implementation acui ON p.assessment_id                    = acui.assessment_id
INNER JOIN course_unit_implementation            cui  ON acui.course_unit_implementation_id = cui.id
INNER JOIN course_implementation                 ci   ON cui.course_implementation_id       = ci.id
INNER JOIN folder                                f    ON ci.folder_id                       = f.id


-- 6: Assessments that live within a project, which lives within a course specification
UNION
SELECT
    a.id   AS assessment_id,
    f.id   AS folder_id,
    p.id   AS project_assessment_id,
    NULL   AS course_implementation_id,
    NULL   AS course_unit_implementation_id,
    cs.id  AS course_specification_id,
    cus.id AS course_unit_specification_id
FROM       assessment                           a
INNER JOIN project_assessment                   pa   ON a.id                              = pa.assessment_id
INNER JOIN project                              p    ON pa.project_id                     = p.id
INNER JOIN assessment_course_unit_specification acus ON p.assessment_id                   = acus.assessment_id
INNER JOIN course_unit_specification            cus  ON acus.course_unit_specification_id = cus.id
INNER JOIN course_specification                 cs   ON cus.course_specification_id       = cs.id
INNER JOIN folder                               f    ON cs.folder_id                      = f.id


-- All ordered by assessment ID by default
ORDER BY assessment_id ASC;
