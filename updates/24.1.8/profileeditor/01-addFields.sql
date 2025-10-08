-- Add the new 'Work Phone' field to staff profiles

INSERT INTO
    `profile_field` 
    (roletype, field, display_as, sequence, enabled, term, icon, css, click_to_reveal, is_external)
VALUES (
        'staff',
        'StaffOccupPhone',
        'Work Phone',
        (
            SELECT MAX(sequence) + 1
            FROM profile_field pf
            WHERE
                roletype = 'staff'
        ),
        0, -- disabled by default
        NULL,
        NULL,
        NULL,
        0,
        1
    );