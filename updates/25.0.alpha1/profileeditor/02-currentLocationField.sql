-- A new field "currentLocation" available to students and staff that shows
-- where the user might be currently located based on their timetable.

INSERT INTO
    `profile_field`
    (roletype, field, display_as, sequence, enabled, term, icon, css, click_to_reveal, is_external)
VALUES (
        'student',
        'currentLocation',
        'Current Location',
        (SELECT MAX(sequence) + 1 FROM profile_field pf WHERE roletype = 'student'),
        1, -- enabled by default
        NULL,
        NULL,
        NULL,
        0,
        0
    ),
    (
        'staff',
        'currentLocation',
        'Current Location',
        (SELECT MAX(sequence) + 1 FROM profile_field pf WHERE roletype = 'staff'),
        0, -- disabled by default
        NULL,
        NULL,
        NULL,
        0,
        0
    );

-- FOR STUDENTS
-- Appears on all student profiles
INSERT INTO profile_field_campus_availability (profile_field_id, campus_id)
    SELECT pf.id, lc.id
    FROM profile_field pf
    JOIN luCampus lc
    WHERE pf.roletype = 'student' AND pf.field = 'currentLocation';

-- Visible for all staff with 'View Student Profile', parents of the student, and the user themself
INSERT INTO profile_field_roletype_visibility (profile_field_id, roletype)
    SELECT pf.id, rt.roletype
    FROM profile_field pf
    JOIN (SELECT 'staff' as roletype UNION SELECT 'parent' UNION SELECT 'self') AS rt
    WHERE pf.roletype = 'student' AND pf.field = 'currentLocation';

-- FOR STAFF
-- Disabled by default, so nothing to do here
