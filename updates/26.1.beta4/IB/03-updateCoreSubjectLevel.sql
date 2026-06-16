-- Update core subject level for TOK and EE to TK and EE respectively.
UPDATE schoolbox.ib_group_subjects gs
INNER JOIN schoolbox.lu_ib_subjects s ON s.id = gs.subject_id
SET gs.level = CASE s.name
    WHEN 'Theory of Knowledge (TOK)' THEN 'TK'
    WHEN 'Extended Essay (EE)' THEN 'EE'
END
WHERE s.name IN ('Theory of Knowledge (TOK)', 'Extended Essay (EE)');
