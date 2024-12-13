-- Add "Staff Code" to terminology
-- Add "Form Teacher" to terminology
-- Add "Add Individual Education Plans" to terminology

INSERT INTO localisation (term, title, singular_form, plural_form, capitalise_first, indefinite_article)
VALUES (
    'staffCode',
    'Staff Code',
    'staff code',
    'staff codes',
    1,
    'a'
), (
    'formTeacher',
    'Class Teacher',
    'class teacher',
    'class teachers',
    1,
    'a'
), (
    'individualEducationPlans',
    'Individual Education Plan',
    'individual education plan',
    'individual education plans',
    1,
    'a'
);

-- Assign terminology to profile fields

UPDATE profile_field
SET term = 'staffCode'
WHERE field = 'SchoolStaffCode'
AND roletype = 'staff'
LIMIT 1;

UPDATE profile_field
SET term = 'formTeacher'
WHERE field = 'formTeacher'
AND roletype = 'student'
LIMIT 1;

UPDATE profile_field
SET term = 'individualEducationPlans'
WHERE field = 'countIndividualEducationPlans'
AND roletype = 'student'
LIMIT 1;
