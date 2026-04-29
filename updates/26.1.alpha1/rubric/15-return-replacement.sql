-- Deprecate the return_id column, allowing it to be null; future saves will never use this column
alter table rubric_capability_mark
    change return_id _return_id int(11) unsigned null;

-- The (feedback_id, feedback_table) tuple now represents where a feedback can be found for any learning activity
-- rather than being assumed to be a submission_return record
alter table rubric_capability_mark
    add feedback_id int unsigned not null after _return_id;

-- TODO Arguably this might be an enum but the views don't do it and we're starting to materialise it here.
--  Doing so means that we'll be changing schema just to add a new feedback location which seems yuk in prod
alter table rubric_capability_mark
    add feedback_table varchar(255) not null after feedback_id;

-- Seed (feedback_id, feedback_table) tuples with equivalents to each return_id
-- These will eventually contain references to non-due-work feedback tables such as reporting_feedback etc
UPDATE rubric_capability_mark rcm
INNER JOIN submission_return sr ON rcm._return_id = sr.id
SET feedback_table = 'submission_return', feedback_id = sr.id

-- TODO not exactly sure how we'd put foreign keys on this; this feedback_id is polymorphic
--  and could be found in multiple tables, not just one.