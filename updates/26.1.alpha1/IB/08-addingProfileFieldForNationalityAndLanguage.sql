SET
    @field_sequence = (SELECT COALESCE(MAX(sequence), 0)
                       FROM profile_field
                       WHERE roletype = 'student');

INSERT INTO profile_field (roletype, field, display_as, sequence, enabled, term, icon, css, options,
                           options_default, is_external)
VALUES ('student',
        'nationality',
        'Nationality',
        @field_sequence + 1,
        0,
        null,
        null,
        null,
        null,
        null,
        0),
       ('student',
        'language',
        'Language',
        @field_sequence + 2,
        0,
        null,
        null,
        null,
        null,
        null,
        0);


-- Appears on all student profiles
-- INSERT INTO profile_field_campus_availability (profile_field_id, campus_id)
-- SELECT pf.id, lc.id
-- FROM profile_field pf
--          JOIN luCampus lc
-- WHERE pf.roletype = 'student'
--   AND pf.field in ('nationality', 'language');

-- Visible for all staff with 'View Student Profile'
-- INSERT INTO profile_field_roletype_visibility (profile_field_id, roletype)
-- SELECT pf.id, rt.roletype
-- FROM profile_field pf
--          JOIN (SELECT 'staff' as roletype
--                UNION
--                SELECT 'parent'
--                UNION
--                SELECT 'self'
--                UNION
--                SELECT 'student') AS rt
-- WHERE pf.roletype = 'student'
--   AND pf.field in ('nationality', 'language');

