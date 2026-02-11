-- This update hides the 'protect_student_contacts' config from the UI, as it is no longer relevant.
UPDATE config
SET
    hidden = true
WHERE
    name = 'protect_student_contacts';
