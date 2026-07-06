-- Removing EE for the time being from the core subject as it's not an available subject for the 2027 sessions
DELETE
FROM ib_group_subjects
WHERE subject_id = (
    SELECT id
    FROM lu_ib_subjects
    WHERE
        name = 'Extended Essay (EE)'
            );

