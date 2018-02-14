ALTER TABLE `submission_box`
ADD `self_reviewability` ENUM('none', 'reflect-only', 'full') NOT NULL;

-- Store the mechanism (ie work-flow) by which this feedback was given.
-- (likely to in future also contain 'peer-reflect' and 'peer-grade')
ALTER TABLE submission_return
  ADD `review_route` ENUM('instructor', 'self-reflection', 'self-grading') NOT NULL AFTER owner;
