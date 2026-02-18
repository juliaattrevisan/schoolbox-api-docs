-- Add qualification terminology for International Baccalaureate system
-- This allows schools to customise the term "qualification" in their terminology settings

INSERT INTO
    localisation (
        term,
        title,
        singular_form,
        plural_form,
        capitalise_first,
        indefinite_article
    )
VALUES (
        'qualification',
        'Qualification',
        'qualification',
        'qualifications',
        1,
        'a'
    );
