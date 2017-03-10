-- Fixes data-integrity problems and bring the data up to date with current system behaviour

-- Delete Course-implementations if their folder is deleted
UPDATE folder f
JOIN course_implementation ci ON ci.folder_id = f.id
SET ci.deleted_at = f.deleted_at
WHERE f.deleted_at IS NOT NULL AND ci.deleted_at IS NULL;

-- Delete unit-implementations belonging to Deleted course-implementations
UPDATE course_implementation ci
JOIN course_unit_implementation ui ON ui.course_implementation_id = ci.id
SET ui.deleted_at = ci.deleted_at
WHERE ci.deleted_at IS NOT NULL AND ui.deleted_at IS NULL;

-- Delete Assessments in Deleted unit-implementations
UPDATE course_unit_implementation ui
JOIN assessment_course_unit_implementation aui ON aui.course_unit_implementation_id = ui.id
JOIN assessment a ON a.id = aui.assessment_id
SET a.deleted_at = ui.deleted_at
WHERE ui.deleted_at IS NOT NULL AND a.deleted_at IS NULL;

-- Delete Project if ProjectAssessment is deleted
UPDATE assessment a
JOIN project p ON p.assessment_id = a.id
SET p.deleted_at = a.deleted_at
WHERE a.deleted_at IS NOT NULL AND p.deleted_at IS NULL;

-- Delete Project' sub-assessments if Project is deleted
UPDATE project p
JOIN project_assessment pa ON pa.project_id = p.id
JOIN assessment a ON a.id = pa.assessment_id
SET a.deleted_at = p.deleted_at
WHERE p.deleted_at IS NOT NULL AND a.deleted_at IS NULL;

-- Delete Quiz if QuizAssessment is deleted
UPDATE assessment a
JOIN assess q ON q.assessment_id = a.id
SET q.deleted_at = a.deleted_at
WHERE a.deleted_at IS NOT NULL AND q.deleted_at IS NULL;

-- Delete Section if Quiz is deleted
UPDATE assess q
JOIN assess_section s ON s.assess_id = q.assess_id
SET s.deleted_at = q.deleted_at
WHERE q.deleted_at IS NOT NULL AND s.deleted_at IS NULL;

-- Delete Question if Section is deleted
UPDATE assess_section s
JOIN assess_question qu ON qu.section_id = s.section_id
SET qu.deleted_at = s.deleted_at
WHERE s.deleted_at IS NOT NULL AND qu.deleted_at IS NULL;

-- Delete Solution if Question is deleted
UPDATE assess_question qu
JOIN assess_answer sol ON sol.question_id = qu.question_id
SET sol.deleted_at = qu.deleted_at
WHERE qu.deleted_at IS NOT NULL AND sol.deleted_at IS NULL;

-- Delete Attempt if Quiz is deleted
UPDATE assess q
JOIN assess_attempt a ON a.assess_id = q.assess_id
SET a.deleted_at = q.deleted_at
WHERE q.deleted_at IS NOT NULL AND a.deleted_at IS NULL;

-- Delete Answer if Attempt is deleted
UPDATE assess_attempt att
JOIN assess_response ans ON ans.attempt_id = att.attempt_id
SET ans.deleted_at = att.deleted_at
WHERE att.deleted_at IS NOT NULL AND ans.deleted_at IS NULL;

-- Delete DueWork if Assessment is deleted
UPDATE assessment a
JOIN submission_box dw ON dw.assessment_id = a.id
SET dw.deleted = TRUE
WHERE a.deleted_at IS NOT NULL AND dw.deleted IS FALSE;

-- Delete Student Submission if Assessment is deleted
UPDATE assessment a
JOIN submission_box dw ON dw.assessment_id = a.id
JOIN student_submission ss ON ss.box_id = dw.id
SET ss.deleted_at = a.deleted_at
WHERE a.deleted_at IS NOT NULL AND ss.deleted_at IS NULL;

-- Delete Submission File if Student Submission is deleted
UPDATE student_submission ss
JOIN student_submission_file ssf ON ssf.student_submission_id = ss.id
SET ssf.deleted_at = ss.deleted_at
WHERE ss.deleted_at IS NOT NULL AND ssf.deleted_at IS NULL;

-- Delete Submission Return if Assessment is deleted
UPDATE assessment a
JOIN submission_box dw ON dw.assessment_id = a.id
JOIN submission_return sr ON sr.box_id = dw.id
SET sr.deleted_at = a.deleted_at
WHERE a.deleted_at IS NOT NULL AND sr.deleted_at IS NULL;

-- Delete Return File if Return is deleted
UPDATE submission_return sr
JOIN submission_return_file srf ON srf.submission_return_id = sr.id
SET srf.deleted_at = sr.deleted_at
WHERE sr.deleted_at IS NOT NULL AND srf.deleted_at IS NULL;

-- Delete Task if Assessment is deleted
UPDATE assessment a
JOIN task t ON t.assessment_id = a.id
SET t.deleted_at = a.deleted_at
WHERE a.deleted_at IS NOT NULL AND t.deleted_at IS NULL;

-- Delete Completion if Task is deleted
UPDATE task t
JOIN task_completion tc ON tc.task_id = t.id
SET tc.deleted_at = t.deleted_at
WHERE t.deleted_at IS NOT NULL AND tc.deleted_at IS NULL;







