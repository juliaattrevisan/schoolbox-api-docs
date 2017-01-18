-- Temporarily permit negative level.sequence. We will insert -1 for levels in the subsequent script when we cannot
-- derive a sequence. Will will then try to derive it in a separate script, then reset back to disable negative.
ALTER TABLE rubric_level
  CHANGE COLUMN sequence sequence INT(11) SIGNED;
