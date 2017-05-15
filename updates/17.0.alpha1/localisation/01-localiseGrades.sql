ALTER TABLE `localisation`
  ADD COLUMN `title` VARCHAR(256) NULL AFTER `term`;


INSERT INTO localisation (term, title, singular_form, plural_form, capitalise_first)
VALUES ('grades', 'Grades','grade', 'grades', 1);

UPDATE `localisation` SET `title`='Calendar' WHERE `term`='calendar';
UPDATE `localisation` SET `title`='News' WHERE `term`='news';
UPDATE `localisation` SET `title`='Resource' WHERE `term`='resource';
UPDATE `localisation` SET `title`='Rubric Indicator' WHERE `term`='rubricCriterion';
UPDATE `localisation` SET `title`='Rubric Capability' WHERE `term`='rubricGroup';
UPDATE `localisation` SET `title`='Rubric Level' WHERE `term`='rubricLevel';
UPDATE `localisation` SET `title`='Special Needs Flag' WHERE `term`='specialNeeds';