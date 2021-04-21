-- Add some evidence tags to get started
SELECT id INTO @adminId FROM user WHERE username = 'alaress';
INSERT INTO lu_evidence_tag (
    name,
    sequence,
    updated_by,
    created_at,
    updated_at,
    deleted_at
) VALUES (
    'Class Work',
    1,
    @adminId,
    NOW(),
    NOW(),
    NULL
), (
    'Extra-Curricular',
    2,
    @adminId,
    NOW(),
    NOW(),
    NULL
), (
    'Goals',
    3,
    @adminId,
    NOW(),
    NOW(),
    NULL
), (
    'Team Work',
    4,
    @adminId,
    NOW(),
    NOW(),
    NULL
);
