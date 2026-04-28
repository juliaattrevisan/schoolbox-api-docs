-- Add a rubric_instance_id column
alter table rubric_capability_usage
    add rubric_instance_id int not null after rubric_capability_id;

-- deprecate submission_box_id
alter table rubric_capability_usage
    change submission_box_id _submission_box_id int(11) unsigned not null;

-- find the rubric_instance_id equivalent of each submission_box_id value
-- that we installed earlier
UPDATE rubric_capability_usage rcu
INNER JOIN submission_box sb ON rcu._submission_box_id = sb.id
INNER JOIN rubric_instance ri ON sb.assessment_id = ri.assessment_id
SET rcu.rubric_instance_id = ri.id;


-- Add a rubric_instance_id column
alter table rubric_indicator_usage
    add rubric_instance_id int not null after rubric_indicator_id;

-- deprecate submission_box_id
alter table rubric_indicator_usage
    change submission_box_id _submission_box_id int(11) unsigned not null;

-- find the rubric_instance_id equivalent of each submission_box_id value
-- that we installed earlier
UPDATE rubric_indicator_usage riu
INNER JOIN submission_box sb ON riu._submission_box_id = sb.id
INNER JOIN rubric_instance ri ON sb.assessment_id = ri.assessment_id
SET riu.rubric_instance_id = ri.id;


-- Add a rubric_instance_id column
alter table rubric_level
    add rubric_instance_id int not null after id;

alter table rubric_level
    change box_id _box_id int(11) unsigned not null;

-- find the rubric_instance_id equivalent of each submission_box_id value
-- that we installed earlier
UPDATE rubric_level rl
INNER JOIN submission_box sb ON rl._box_id = sb.id
INNER JOIN rubric_instance ri ON sb.assessment_id = ri.assessment_id
SET rl.rubric_instance_id = ri.id;
