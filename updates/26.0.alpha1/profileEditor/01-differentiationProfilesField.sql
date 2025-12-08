-- A new Differentiation Profiles field that is shown for all students

INSERT INTO
    `profile_field` (
        roletype,
        field,
        display_as,
        sequence,
        enabled,
        term,
        icon,
        css,
        options,
        options_default,
        is_external
    )
VALUES (
        'student',
        'differentiationProfile',
        'Differentiation Profile',
        (
            SELECT MAX(sequence) + 1
            FROM profile_field pf
            WHERE
                roletype = 'student'
        ),
        1, -- enabled by default
        NULL,
        'icon-learning-support',
        'highlight',
        '{"clickToReveal": 1}',
        '{"clickToReveal": 1}',
        0
    );

-- Update the sequence of the new differentiationProfile field to be right after the portrait field
UPDATE `profile_field`
SET
    sequence = (
        SELECT sequence + 1
        FROM (
                SELECT sequence
                FROM profile_field
                WHERE
                    roletype = 'student'
                    AND field = 'portrait'
            ) AS portrait_seq
    )
WHERE
    roletype = 'student'
    AND field = 'differentiationProfile';

-- Increment the sequence of all other student fields that are at or above the new differentiationProfile sequence
UPDATE `profile_field`
SET
    sequence = sequence + 1
WHERE
    roletype = 'student'
    AND field != 'differentiationProfile'
    AND sequence >= (
        SELECT sequence
        FROM (
                SELECT sequence
                FROM profile_field
                WHERE
                    roletype = 'student'
                    AND field = 'differentiationProfile'
            ) AS diff_seq
    );

-- Appears on all student profiles
INSERT INTO
    profile_field_campus_availability (profile_field_id, campus_id)
SELECT pf.id, lc.id
FROM profile_field pf
    JOIN luCampus lc
WHERE
    pf.roletype = 'student'
    AND pf.field = 'differentiationProfile';

-- Visible for all staff with 'View Student Profile'
INSERT INTO
    profile_field_roletype_visibility (profile_field_id, roletype)
SELECT pf.id, rt.roletype
FROM profile_field pf
    JOIN (
        SELECT 'staff' as roletype
    ) AS rt
WHERE
    pf.roletype = 'student'
    AND pf.field = 'differentiationProfile';
