-- Deprecate the return_id column, allowing it to be null; future saves will never use this column
alter table rubric_capability_mark
    change return_id _return_id int(11) unsigned null;

-- Store a rubric instance here so you what marks belonged to a particular grid instance
alter table rubric_capability_mark
    add rubric_instance_id int unsigned not null after _return_id;

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
INNER JOIN submission_box sb ON sr.box_id = sb.id
INNER JOIN assessment a ON sb.assessment_id = a.id
INNER JOIN rubric_instance ri ON ri.assessment_id = a.id
SET
    rubric_instance_id = ri.id,
    feedback_table = 'submission_return',
    feedback_id = sr.id;

-- Add a foreign key where possible
ALTER TABLE rubric_level
ADD CONSTRAINT fk_rcm_ri FOREIGN KEY (rubric_instance_id)
REFERENCES rubric_instance (id);

-- TODO not exactly sure how we'd put other foreign keys on this; this feedback_id is polymorphic
--  and could be found in multiple tables, not just one.