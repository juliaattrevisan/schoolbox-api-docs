CREATE OR REPLACE VIEW SCHOOLBOX_TIMETABLE_DAYS AS
SELECT
	edumate.timetable.timetable_id,
	edumate.term.start_date,
	edumate.term.end_date,
	edumate.term.cycle_start_day,
	edumate.cycle.days_in_cycle
FROM edumate.timetable
INNER JOIN edumate.term ON edumate.term.timetable_id = edumate.timetable.timetable_id
INNER JOIN edumate.term_group ON edumate.term.term_id = edumate.term_group.term_id
INNER JOIN edumate.cycle ON edumate.cycle.cycle_id = edumate.term_group.cycle_id
;

CREATE OR REPLACE VIEW SCHOOLBOX_TIMETABLE_DEFINITION AS
SELECT DISTINCT
        edumate.timetable.timetable_id AS "CAMPUS",
        edumate.period.period,
        edumate.period.short_name AS "PERIOD_NUMBER",
        edumate.period.start_time,
        edumate.period.end_time,
        edumate.cycle_day.day_index AS "DAY_NUMBER"
FROM edumate.period_cycle_day
INNER JOIN edumate.cycle_day ON edumate.cycle_day.cycle_day_id = edumate.period_cycle_day.cycle_day_id
INNER JOIN edumate.period ON edumate.period.period_id = edumate.period_cycle_day.period_id
INNER JOIN edumate.term_group ON edumate.term_group.cycle_id = edumate.cycle_day.cycle_id
INNER JOIN edumate.term ON edumate.term.term_id = edumate.term_group.term_id
INNER JOIN edumate.timetable ON edumate.timetable.timetable_id = edumate.term.timetable_id
INNER JOIN edumate.cycle ON edumate.cycle.cycle_id = edumate.cycle_day.cycle_id
WHERE edumate.cycle_day.day_index <= edumate.cycle.days_in_cycle
AND edumate.term.start_date <= current date
AND edumate.term.end_date >= current date
;

CREATE OR REPLACE VIEW SCHOOLBOX_TIMETABLE AS
SELECT
	edumate.cycle_day.day_index as "DAY_NUMBER",
	edumate.period.short_name as "PERIOD_NUMBER",
	edumate.course.code||'.'||edumate.class.identifier AS "CLASS_CODE",
	edumate.staff.staff_number,
	edumate.room.code AS "ROOM",
	edumate.timetable.timetable_id AS "CAMPUS"
FROM edumate.period_class
INNER JOIN edumate.period_cycle_day ON edumate.period_cycle_day.period_cycle_day_id = edumate.period_class.period_cycle_day_id
INNER JOIN edumate.cycle_day ON edumate.cycle_day.cycle_day_id = edumate.period_cycle_day.cycle_day_id
INNER JOIN edumate.period ON edumate.period.period_id = edumate.period_cycle_day.period_id
INNER JOIN edumate.class ON edumate.class.class_id = edumate.period_class.class_id
INNER JOIN edumate.course ON edumate.course.course_id = edumate.class.course_id
INNER JOIN edumate.perd_cls_teacher ON edumate.perd_cls_teacher.period_class_id = edumate.period_class.period_class_id
	AND edumate.perd_cls_teacher.is_primary = 1
INNER JOIN edumate.teacher ON edumate.teacher.teacher_id = edumate.perd_cls_teacher.teacher_id
INNER JOIN edumate.staff ON edumate.staff.contact_id = edumate.teacher.contact_id
INNER JOIN edumate.room ON edumate.room.room_id = edumate.period_class.room_id
INNER JOIN edumate.timetable ON edumate.timetable.timetable_id = edumate.period_class.timetable_id
INNER JOIN edumate.contact ON edumate.staff.contact_id = edumate.contact.contact_id
WHERE
(
	current date BETWEEN (
	CASE
		WHEN edumate.period_class.effective_start = edumate.timetable.computed_start_date
		THEN edumate.timetable.computed_v_start_date
		ELSE edumate.period_class.effective_start
	END
	)
	AND edumate.period_class.effective_end
)
AND
(
	current date BETWEEN (
	CASE
		WHEN edumate.period_class.effective_start = edumate.timetable.computed_start_date
		THEN edumate.timetable.computed_v_start_date
		ELSE edumate.period_class.effective_start
	END
	)
	AND edumate.timetable.computed_end_date
)
;
