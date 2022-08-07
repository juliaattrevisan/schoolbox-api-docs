# Create a hole for pastoral student access
UPDATE config c
INNER JOIN lu_config_type lct on c.type_id = lct.id
SET c.sequence = c.sequence + 1
WHERE lct.type = 'Pastoral Care'
AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='pastoral_parent_access' LIMIT 1);

# Add pastoral student access
INSERT INTO config
(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES (
    'pastoral_student_access',
    '0',
    'Allow Student Pastoral Access',
    'If toggled to ''ON'', students will be able to receive pastoral records targeted to themselves.',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    null,
    (SELECT id FROM lu_config_type WHERE type = 'Pastoral Care'),
    'true',
    -- hide for all except wellbeing,elite
    (IF((SELECT * FROM (SELECT COUNT(*) FROM config WHERE name = 'site_plan' AND value IN ('wellbeing','elite')) t1), 'false', 'true')),
    'false',
    'settings',
    (SELECT c.sequence+1 FROM config c WHERE c.name='pastoral_parent_access' LIMIT 1)
);
