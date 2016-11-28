/**
 * Set anything using the obsolete "free" mark type to the "percent" mark type.
 *
 * I don't think that free marks have been used since 2012 or before.
 */
UPDATE submission_box
SET    mark_type = (SELECT mark_type_id FROM submission_mark_type WHERE slug = 'percent')
WHERE  mark_type = (SELECT mark_type_id FROM submission_mark_type WHERE slug = 'free');
