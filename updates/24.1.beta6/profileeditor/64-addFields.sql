-- Add the new 'barcode' field to student profiles

INSERT INTO
    `profile_field` 
    (roletype, field, display_as, sequence, enabled, term, icon, css, click_to_reveal, is_external)
VALUES (
        'student',
        'barcode',
        'Barcode (Student ID)',
        (
            SELECT MAX(sequence) + 1
            FROM profile_field pf
            WHERE
                roletype = 'student'
        ),
        0, -- disabled by default
        NULL,
        NULL,
        NULL,
        0,
        0
    );
