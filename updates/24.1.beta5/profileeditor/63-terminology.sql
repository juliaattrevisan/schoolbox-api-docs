-- Add "Staff Code" to terminology

INSERT INTO localisation (term, title, singular_form, plural_form, capitalise_first, indefinite_article)
VALUES (
    'staffCode',
        'Staff Code',
        'staff code',
        'staff codes',
        1,
        'a'
);

















-- Add Individual Education Plans to terminology
-- and assign it to corresponding profile field

INSERT INTO localisation (term, title, singular_form, plural_form, capitalise_first, indefinite_article)
VALUES (
    'individualEducationPlans',
    'Individual Education Plan',
    'individual education plan',
    'individual education plans',
    1,
    'a'
);

UPDATE profile_field
SET term = 'individualEducationPlans'
WHERE field = 'countIndividualEducationPlans'
LIMIT 1;
