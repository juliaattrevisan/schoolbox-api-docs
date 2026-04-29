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

