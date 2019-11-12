-- Regrettably, we cannot use the PHP code to perform DB manipulations during updates
-- as the codebase may not align to the Schema.

-- Excluded Task participants
UPDATE task_completion tc
JOIN task t ON t.id = tc.task_id
JOIN participant p ON tc.student_id = p.user_id
JOIN participant_plan pp ON p.participant_plan_id = pp.id AND t.assessment_id = pp.assessment_id
SET tc.deleted_at = IF(tc.deleted_at IS NULL OR tc.deleted_at > p.excluded_at, p.excluded_at, tc.deleted_at)
WHERE p.excluded_at IS NOT NULL;

-- Excluded Quiz participants
UPDATE assess_attempt attempt
JOIN assess_response response ON response.attempt_id = attempt.attempt_id
JOIN assess quiz ON quiz.assess_id = attempt.assess_id
JOIN participant p ON attempt.user_id = p.user_id
JOIN participant_plan pp ON p.participant_plan_id = pp.id AND quiz.assessment_id = pp.assessment_id
SET attempt.deleted_at = IF(attempt.deleted_at IS NULL OR attempt.deleted_at > p.excluded_at, p.excluded_at, attempt.deleted_at),
    response.deleted_at = IF(response.deleted_at IS NULL OR response.deleted_at > p.excluded_at, p.excluded_at, response.deleted_at)
WHERE p.excluded_at IS NOT NULL;

-- Excluded SubBox participants (submissions)
UPDATE student_submission ss
LEFT JOIN student_submission_file ssf ON ssf.student_submission_id = ss.id
LEFT JOIN plagscan_document plagscan ON plagscan.student_submission_file_id = ssf.id
LEFT JOIN student_submission_file_kami kami ON kami.student_submission_file_id = ssf.id
JOIN submission_box sb ON sb.id = ss.box_id
JOIN participant p ON ss.owner = p.user_id
JOIN participant_plan pp ON p.participant_plan_id = pp.id AND sb.assessment_id = pp.assessment_id
SET ss.deleted_at = IF(ss.deleted_at IS NULL OR ss.deleted_at > p.excluded_at, p.excluded_at, ss.deleted_at),
    ssf.deleted_at = IF(ssf.deleted_at IS NULL OR ssf.deleted_at > p.excluded_at, p.excluded_at, ssf.deleted_at),
    kami.deleted_at = IF(kami.deleted_at IS NULL OR kami.deleted_at > p.excluded_at, p.excluded_at, kami.deleted_at)
WHERE p.excluded_at IS NOT NULL;

-- Excluded SubBox participants (feedback)
UPDATE submission_return sr
LEFT JOIN submission_return_file srf ON srf.submission_return_id = sr.id
JOIN submission_box sb ON sb.id = sr.box_id
JOIN participant p ON sr.owner = p.user_id
JOIN participant_plan pp ON p.participant_plan_id = pp.id AND sb.assessment_id = pp.assessment_id
SET sr.deleted_at = IF(sr.deleted_at IS NULL OR sr.deleted_at > p.excluded_at, p.excluded_at, sr.deleted_at),
    srf.deleted_at = IF(srf.deleted_at IS NULL OR srf.deleted_at > p.excluded_at, p.excluded_at, srf.deleted_at)
WHERE p.excluded_at IS NOT NULL;

