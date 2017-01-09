-- Link Marks to Descriptors if:
-- * Mark's Descriptor is NULL
-- * There exists some descriptor:
-- * * WHERE mark.subBox = descriptor.subBox,
-- * * AND mark.criterion = descriptor.criterion
-- * * AND mark.mark = descriptor.value
-- * * AND mark.mark IS NOT NULL
-- * * AND descriptor.level.subBox = mark.subBox

-- Interestingly, for ???, there exist 2969 matches. All with `mark`.`tstamp` between 2015-10-07 and 2015-11-20
-- Also tellingly, of the descriptors that match, none have marks.

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

