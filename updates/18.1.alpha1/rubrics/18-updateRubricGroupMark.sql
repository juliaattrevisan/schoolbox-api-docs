/**
 * Populate rubric group usage table, with maximum criterion values for each
 * criterion used by the due work.
 *
 * Question: how are invalidated marks handled?
 * * "Changng
 */
INSERT INTO rubric_group_mark
    (rubric_group_id, return_id, value, valid)
SELECT
    SUM(rdm.value),
    rdm.return_id,
    rc.rubric_group_id,
    -- Assume that the group mark is only invalid if all the descriptor marks are
    MAX(rdm.valid)
FROM rubric_descriptor_mark rdm
INNER JOIN rubric_descriptor rd ON rdm.descriptor_id=rd.id
INNER JOIN rubric_criterion  rc ON rd.criterion_id=rc.id
GROUP BY rdm.return_id, rc.rubric_group_id;
