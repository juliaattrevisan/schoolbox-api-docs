ALTER TABLE assessment_view ADD COLUMN feedback_was_visible tinyint(1) unsigned NOT NULL DEFAULT FALSE;

# Values defined in Assessment/Component/SubmissionBox.php.
SET @MARK_VISIBILITY_STAFF_ONLY           = 0;
SET @MARK_VISIBILITY_STAFF_AND_STUDENT    = 1;
SET @MARK_VISIBILITY_STAFF_STUDENT_PARENT = 2;

UPDATE assessment_view av
JOIN user u                         ON av.user_id = u.id
JOIN groups g                       ON u.group_id = g.id
LEFT JOIN v_assessment_feedback vaf ON av.assessment_id = vaf.assessment_id
LEFT JOIN project p                 ON av.assessment_id = p.assessment_id
LEFT JOIN lti_assessment ltia       ON av.assessment_id = ltia.assessment_id
LEFT JOIN submission_box sb         ON av.assessment_id = sb.assessment_id
LEFT JOIN task t                    ON av.assessment_id = t.assessment_id
LEFT JOIN assess a                  ON av.assessment_id = a.assessment_id
LEFT JOIN lesson_plan lp            ON av.assessment_id = lp.assessment_id
SET av.feedback_was_visible = IF(
    vaf.published_at <= av.last_visit, # Feedback existed at time of view.
    # Feedback existed at the time of the view, so feedback_was_visible
    # should be true if assess.mark_visible includes the viewer's user type.
    (
        SELECT CASE (
            COALESCE(
                IF(t.id              , NULL                , NULL), # Mark visibility does not apply to tasks.
                IF(a.assessment_id   , a.mark_visibility   , NULL),
                IF(ltia.assessment_id, ltia.mark_visibility, NULL),
                IF(sb.id             , sb.mark_visibility  , NULL),
                IF(p.id              , p.mark_visibility   , NULL),
                IF(lp.id             , NULL                , NULL)  # Mark visibility does not apply to lesson plans.
            )
        )
            WHEN @MARK_VISIBILITY_STAFF_ONLY
                THEN IF(g.group_type IN ('staff'), TRUE, FALSE)
            WHEN @MARK_VISIBILITY_STAFF_AND_STUDENT
                THEN IF(g.group_type IN ('staff', 'student'), TRUE, FALSE)
            WHEN @MARK_VISIBILITY_STAFF_STUDENT_PARENT
                THEN IF(g.group_type IN ('staff', 'student', 'parent'), TRUE, FALSE)
            ELSE FALSE
        END
    ),
    FALSE
);
