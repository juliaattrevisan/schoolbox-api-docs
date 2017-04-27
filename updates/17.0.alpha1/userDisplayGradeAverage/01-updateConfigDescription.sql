-- We no longer show the grade graph at all if comparative reporting is off.
-- Therefore, we repurpose display_grade_graph to be a default setting,
-- and merge it with the (now deprecated) display_grade_average setting.

SELECT @displayGradeAverage := `value` FROM `config` WHERE `name` = 'display_grade_average';

UPDATE `config`
SET
  `title`='Display Graph on Student Grade Card By Default',
  `description`='Should the comparative grade graph display for new students? (This setting can be changed on a per-student basis.)',
  `value` = `value` IS TRUE AND @displayGradeAverage IS TRUE
WHERE `name` = 'display_grade_graph';

-- Deprecate display_grade_average setting
UPDATE `config`
SET
  `editable`='false',
  `hidden`='true'
WHERE `name` = 'display_grade_average';
