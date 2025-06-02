-- Ensure all new comment fields are reset to NULL
UPDATE `submission_return` SET `comment` = null WHERE `_comment` is not null;
UPDATE `assess_attempt` SET `comment` = null where `_comment` is not null;
UPDATE `lti_assessment_score` SET `comment` = null where `_comment` is not null;
