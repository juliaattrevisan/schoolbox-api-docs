-- Pre-set any empty comment fields:
-- beta1 converted blank comments to <p></p>,
-- so this fixes it for the staging instances that were upgraded already.
UPDATE `submission_return` SET `comment` = "" WHERE `_comment` = "";
UPDATE `assess_attempt` SET `comment` = "" WHERE `_comment` = "";
UPDATE `lti_assessment_score` SET `comment` = "" WHERE `_comment` = "";
