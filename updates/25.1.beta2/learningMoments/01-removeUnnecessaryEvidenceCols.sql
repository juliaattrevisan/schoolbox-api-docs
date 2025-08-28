-- These were all invented by beta1, so safe to just drop them

-- type isn't used for anything
ALTER TABLE moment_evidence DROP COLUMN `type`;

-- hash should always be required
ALTER TABLE moment_evidence
CHANGE COLUMN `hash` `hash` VARCHAR(100) CHARSET ascii NOT NULL;
