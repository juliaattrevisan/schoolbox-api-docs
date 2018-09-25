/**
 * Populate rubric group usage table, with maximum criterion values for each
 * criterion used by the due work.
 */

INSERT INTO rubric_group_usage
    (rubric_group_id, submission_box_id, max_value, sequence)

-- Outer query: sum max criterion values for each rubric group/due work
SELECT
    temp.rubric_group_id,
    temp.submission_box_id,
    SUM(temp.max_value_for_criterion_and_box) AS max_value,
    0 AS sequence
FROM (
    -- Inner query: fetch all criteria+groups in use on each due work,
    -- and the max descriptor value
    SELECT
        rc.id AS rubric_criterion_id,
        rg.id AS rubric_group_id,
        ru.submission_box_id,
        MAX(rd.value) as max_value_for_criterion_and_box
    FROM rubric_criterion rc
        INNER JOIN rubric_group           rg ON rc.rubric_group_id=rg.id
        INNER JOIN rubric_criterion_usage ru ON rc.id=ru.criterion_id
        INNER JOIN rubric_descriptor      rd ON rc.id=rd.criterion_id
        INNER JOIN rubric_level           rl ON rd.level_id=rl.id AND ru.submission_box_id=rl.box_id
    WHERE
        rc.deleted_at IS NULL
        AND rg.deleted_at IS NULL
        AND rd.deleted_at IS NULL
        AND rd.value IS NOT NULL
    GROUP BY
        rc.id,
        rc.rubric_group_id,
        ru.submission_box_id
    ) AS temp
GROUP BY
    temp.rubric_group_id,
    temp.submission_box_id
;
