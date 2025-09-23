-- A new field "iFrame" available to students/staff/parents that lets you add an iframe to any profile

INSERT INTO
    `profile_field`
    (roletype, field, display_as, sequence, enabled, term, icon, css, click_to_reveal, is_external)
VALUES (
        'student',
        'iframe',
        'HTML',
        (SELECT MAX(sequence) + 1 FROM profile_field pf WHERE roletype = 'student'),
        0, -- disabled by default
        NULL,
        NULL,
        NULL,
        0,
        0
    ),
    (
        'staff',
        'iframe',
        'HTML',
        (SELECT MAX(sequence) + 1 FROM profile_field pf WHERE roletype = 'staff'),
        0, -- disabled by default
        NULL,
        NULL,
        NULL,
        0,
        0
    ),
    (
        'parent',
        'iframe',
        'HTML',
        (SELECT MAX(sequence) + 1 FROM profile_field pf WHERE roletype = 'parent'),
        0, -- disabled by default
        NULL,
        NULL,
        NULL,
        0,
        0
    );
