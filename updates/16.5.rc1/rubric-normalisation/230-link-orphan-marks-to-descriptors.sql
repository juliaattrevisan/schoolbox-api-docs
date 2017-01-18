-- !IMPORTANT: NEED TO RUN THIS AFTER CREATING LEVELS+DESCRIPTORS FOR LEGACY RESULT SETS

-- There are cases of marks having null pointers to descriptors, but we can use redundant data to generate the link.
-- On SEDA we have 2969 such marks, all with `mark`.`tstamp` between 2015-10-07 and 2015-11-20.

-- Link Marks to Descriptors if:
-- * mark.descriptor_id is NULL
-- * There exists some descriptor:
-- * * WHERE mark.subBox = descriptor.subBox,
-- * * AND mark.criterion = descriptor.criterion
-- * * AND mark.mark = descriptor.value
-- * * AND mark.mark IS NOT NULL
-- * * AND descriptor.level.subBox = mark.subBox
UPDATE rubric_mark rm
JOIN rubric_descriptor rd
  ON  rd.value             = rm.mark
  AND rm.submission_box_id = rd.submission_box_id
  AND rm.criterion_id      = rd.criterion_id
JOIN rubric_level rl
  ON  rl.id                = rd.level_id
  AND rl.box_id            = rm.submission_box_id
SET rm.descriptor_id = rd.id
WHERE rm.descriptor_id IS NULL;
