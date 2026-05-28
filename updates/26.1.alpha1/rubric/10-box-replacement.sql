-- Rubric Capability Usage
-- Add a rubric_instance_id column
alter table rubric_capability_usage
    add rubric_instance_id int unsigned null after rubric_capability_id;

-- deprecate submission_box_id
alter table rubric_capability_usage
    change submission_box_id _submission_box_id int(11) unsigned not null;

-- find the rubric_instance_id equivalent of each submission_box_id value
-- that we installed earlier
UPDATE rubric_capability_usage rcu
INNER JOIN submission_box sb ON rcu._submission_box_id = sb.id
INNER JOIN rubric_instance ri ON sb.assessment_id = ri.assessment_id
INNER JOIN assessment a ON sb.assessment_id = a.id
SET rcu.rubric_instance_id = ri.id;


-- Rubric Indicator Usage
-- Add a rubric_instance_id column
alter table rubric_indicator_usage
    add rubric_instance_id int unsigned null after rubric_indicator_id;

-- deprecate submission_box_id
alter table rubric_indicator_usage
    change submission_box_id _submission_box_id int(11) unsigned not null;

-- find the rubric_instance_id equivalent of each submission_box_id value
-- that we installed earlier
UPDATE rubric_indicator_usage riu
INNER JOIN submission_box sb ON riu._submission_box_id = sb.id
INNER JOIN rubric_instance ri ON sb.assessment_id = ri.assessment_id
INNER JOIN assessment a ON sb.assessment_id = a.id
SET riu.rubric_instance_id = ri.id;


-- Rubric Level
-- Add a rubric_instance_id column
alter table rubric_level
    add rubric_instance_id int unsigned null after id;

alter table rubric_level
    change box_id _box_id int(11) unsigned not null;

-- find the rubric_instance_id equivalent of each submission_box_id value
-- that we installed earlier
UPDATE rubric_level rl
INNER JOIN submission_box sb ON rl._box_id = sb.id
INNER JOIN rubric_instance ri ON sb.assessment_id = ri.assessment_id
INNER JOIN assessment a ON sb.assessment_id = a.id
SET rl.rubric_instance_id = ri.id;


-- Foreign Keys for everything
-- add a fk that couldn't have existed previously
ALTER TABLE rubric_capability_usage
ADD INDEX `fk_rcu_ri_idx` (rubric_instance_id ASC);

ALTER TABLE rubric_capability_usage
ADD CONSTRAINT fk_rcu_ri
FOREIGN KEY (rubric_instance_id)
REFERENCES rubric_instance (id)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

-- add a fk that couldn't have existed previously
ALTER TABLE rubric_indicator_usage
ADD INDEX `fk_riu_ri_idx` (rubric_instance_id ASC);

ALTER TABLE rubric_indicator_usage
ADD CONSTRAINT fk_riu_ri
FOREIGN KEY (rubric_instance_id)
REFERENCES rubric_instance (id)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

-- add a fk that couldn't have existed previously
ALTER TABLE rubric_level
ADD INDEX `fk_rl_ri_idx` (rubric_instance_id ASC);

ALTER TABLE rubric_level
ADD CONSTRAINT fk_rl_ri FOREIGN KEY (rubric_instance_id)
REFERENCES rubric_instance (id);


-- Rows that still have no rubric instance cannot participate in the new primary key or views.
-- These are orphaned historical rows that no longer resolve through submission_box -> assessment.
DELETE FROM rubric_capability_usage
WHERE rubric_instance_id IS NULL;

DELETE FROM rubric_indicator_usage
WHERE rubric_instance_id IS NULL;


-- Maybe controversial: update foreign keys and int unsigned.
--
-- Remove old foreign keys
-- These were replaced with foreign keys to rubric_instance_id instead.

# -- rcu has a composite primary key: a (rubric_capability_id, rubric_instance_id) tuple
alter table rubric_capability_usage
    drop foreign key fk_rubric_group_usage_box_id;

alter table rubric_capability_usage
    drop primary key;

alter table rubric_capability_usage
    add primary key (rubric_capability_id, rubric_instance_id);

drop index submission_box_id on rubric_capability_usage;

-- This was previously mandatory (non-null) but it's been discontinued. Saving rows to the db causes crashes because it
-- expects there to be a value here

ALTER TABLE rubric_capability_usage
    MODIFY _submission_box_id INT NULL;

-- riu has a composite primary key: a (rubric_indicator_id, rubric_instance_id) tuple
alter table rubric_indicator_usage
    drop foreign key fk_rubric_usage_box_id;

alter table rubric_indicator_usage
    drop primary key;

alter table rubric_indicator_usage
    add primary key (rubric_indicator_id, rubric_instance_id);

drop index submission_box_id on rubric_indicator_usage;

-- This was previously mandatory (non-null) but it's been discontinued. Saving rows to the db causes crashes because it
-- expects there to be a value here

ALTER TABLE rubric_indicator_usage
    MODIFY _submission_box_id INT NULL;

alter table rubric_level
    drop foreign key fk_rubric_level_box_id;

-- This was previously mandatory (non-null) but it's been discontinued. Saving rows to the db causes crashes because it
-- expects there to be a value here

ALTER TABLE rubric_level
MODIFY _box_id INT NULL;
