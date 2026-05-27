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
SET rcu.rubric_instance_id = ri.id
WHERE sb.use_rubric = 1;


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
SET riu.rubric_instance_id = ri.id
WHERE sb.use_rubric = 1;


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
SET rl.rubric_instance_id = ri.id
WHERE sb.use_rubric = 1;


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


-- Note: rubric_capability_usage and rubric_indicator_usage could still contain rows where rubric_instance_id is null.
--
-- That's because these tables retained rubric usages even for deleted assessments; presumably, this is for the occasion
-- where the school wanted to restore these assessments, rubrics and all.
--
-- We didn't seed rubric_instance with data from deleted assessments. These rows could/should be deleted once we
-- are sure that nobody cares for them anymore (I expect this should be the case given they're related to assessments
-- that no longer functionally exist; the data retention tool that eventually deletes assessments should also eventually
-- delete rubric usages associated with them, perhaps)
--
-- Alternatively, seed the rubric_instance table even for deleted assessments under the assumption that one day they
-- could be restored for a raft of reasons (we don't know why they were deleted in the first place)


-- This was previously mandatory (non-null) but it's been discontinued. Saving rows to the db causes crashes because it
-- expects there to be a value here
ALTER TABLE rubric_capability_usage
MODIFY _submission_box_id INT UNSIGNED NULL;

ALTER TABLE rubric_indicator_usage
MODIFY _submission_box_id INT UNSIGNED NULL;

ALTER TABLE rubric_level
MODIFY _box_id INT UNSIGNED NULL;
