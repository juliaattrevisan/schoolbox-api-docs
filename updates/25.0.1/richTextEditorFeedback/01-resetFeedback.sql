-- Ensure all new comment fields are reset to NULL
UPDATE `submission_return` SET `comment` = null;
UPDATE `assess_attempt` SET `comment` = null;
UPDATE `lti_assessment_score` SET `comment` = null;
