ALTER TABLE assessment_view ADD COLUMN published_feedback_existed tinyint(1) unsigned NOT NULL DEFAULT FALSE;
ALTER TABLE assessment_view ADD COLUMN feedback_published_at datetime DEFAULT NULL;
ALTER TABLE assessment_view ADD COLUMN assessment_type varchar(32) DEFAULT NULL;
ALTER TABLE assessment_view ADD COLUMN user_group_type varchar(32) DEFAULT NULL;
ALTER TABLE assessment_view ADD COLUMN mark_visibility int unsigned DEFAULT NULL;
ALTER TABLE assessment_view ADD COLUMN feedback_was_visible tinyint(1) unsigned NOT NULL DEFAULT FALSE COMMENT "True if feedback existed and was visible to user at the time the assessment was viewed.";

# Values defined in Assessment/Component/SubmissionBox.php.
SET @MARK_VISIBILITY_STAFF_ONLY           = 0;
SET @MARK_VISIBILITY_STAFF_AND_STUDENT    = 1;
SET @MARK_VISIBILITY_STAFF_STUDENT_PARENT = 2;

UPDATE assessment_view av
JOIN user u                         ON av.user_id = u.id
JOIN groups g                       ON u.group_id = g.id
LEFT JOIN project p                 ON av.assessment_id = p.assessment_id
LEFT JOIN lti_assessment ltia       ON av.assessment_id = ltia.assessment_id
LEFT JOIN submission_box sb         ON av.assessment_id = sb.assessment_id
LEFT JOIN task t                    ON av.assessment_id = t.assessment_id
LEFT JOIN assess a                  ON av.assessment_id = a.assessment_id
LEFT JOIN lesson_plan lp            ON av.assessment_id = lp.assessment_id
SET av.feedback_was_visible = IF(
    (
        SELECT EXISTS(
            SELECT *
            FROM v_assessment_feedback
            WHERE assessment_id = av.assessment_id
            AND published_at < av.last_visit
        )
    ),
    # Published feedback existed at the time of the last visit,
    # so feedback_was_visible should be true if assess.mark_visible includes the viewer's user type.
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
    # Feedback did not exist at the time of the last visit,
    # so feedback_was_visible should be false.
    FALSE
),
av.published_feedback_existed = IF(
    (
        SELECT EXISTS(
            SELECT *
            FROM v_assessment_feedback
            WHERE assessment_id = av.assessment_id
            AND published_at < av.last_visit
        )
    ),
    TRUE,
    FALSE
),
av.feedback_published_at = (
    SELECT published_at
    FROM v_assessment_feedback
    WHERE assessment_id = av.assessment_id
    AND published_at < av.last_visit
    ORDER BY published_at DESC
    LIMIT 1
),
av.assessment_type = COALESCE(
    IF(t.id              , 'task'          , NULL), # Mark visibility does not apply to tasks.
    IF(a.assessment_id   , 'quiz'          , NULL),
    IF(ltia.assessment_id, 'lti assessment', NULL),
    IF(sb.id             , 'due work'      , NULL),
    IF(p.id              , 'project'       , NULL),
    IF(lp.id             , 'lesson plan'   , NULL)  # Mark visibility does not apply to lesson plans.
),
av.mark_visibility = COALESCE(
    IF(t.id              , NULL                , NULL), # Mark visibility does not apply to tasks.
    IF(a.assessment_id   , a.mark_visibility   , NULL),
    IF(ltia.assessment_id, ltia.mark_visibility, NULL),
    IF(sb.id             , sb.mark_visibility  , NULL),
    IF(p.id              , p.mark_visibility   , NULL),
    IF(lp.id             , NULL                , NULL)  # Mark visibility does not apply to lesson plans.
),
av.user_group_type = g.group_type;
