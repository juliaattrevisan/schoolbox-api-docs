CREATE OR REPLACE VIEW SCHOOLBOX_CLASSES AS
select distinct
coalesce(edumate.class.class, edumate.class.print_name, edumate.course.course, edumate.course.print_name) as "CLASS",
edumate.course.code||'.'||edumate.class.identifier AS "CLASS_CODE"
from edumate.view_student_class_enrolment
inner join edumate.class on edumate.class.class_id=edumate.view_student_class_enrolment.class_id
inner join edumate.course on edumate.course.course_id=edumate.class.course_id
inner join edumate.student_form_run on edumate.student_form_run.student_id=edumate.view_student_class_enrolment.student_id
inner join edumate.form_run on edumate.form_run.form_run_id=edumate.student_form_run.form_run_id
inner join edumate.timetable on edumate.timetable.timetable_id=edumate.form_run.timetable_id
and edumate.timetable.academic_year_id=edumate.view_student_class_enrolment.academic_year_id
where
current date <= edumate.student_form_run.end_date
and
current date <= edumate.view_student_class_enrolment.end_date
and
current date >= (
case
when edumate.student_form_run.start_date_locked=1 then edumate.timetable.computed_v_start_date
else edumate.student_form_run.start_date
end
)
and
current date >= (
case
when edumate.view_student_class_enrolment.start_date_locked=1 then edumate.timetable.computed_v_start_date
else edumate.view_student_class_enrolment.start_date
end
)
;

CREATE OR REPLACE VIEW SCHOOLBOX_REPORT_PERIODS AS
SELECT
report_period,
start_date,
end_date
FROM EDUMATE.report_period
;

CREATE OR REPLACE VIEW SCHOOLBOX_STUDENTCLASS AS
select
edumate.student.student_number,
edumate.course.code||'.'||edumate.class.identifier AS "CLASS_CODE",
edumate.class_type.class_type
from edumate.view_student_class_enrolment
inner join edumate.student on edumate.student.student_id = edumate.view_student_class_enrolment.student_id
inner join edumate.class on edumate.class.class_id = edumate.view_student_class_enrolment.class_id
inner join edumate.course on edumate.course.course_id = edumate.class.course_id
inner join edumate.class_type on edumate.class.class_type_id = edumate.class_type.class_type_id
inner join edumate.student_form_run on edumate.student_form_run.student_id = edumate.student.student_id
inner join edumate.form_run on edumate.form_run.form_run_id = edumate.student_form_run.form_run_id
inner join edumate.timetable
on edumate.timetable.timetable_id = edumate.form_run.timetable_id
and edumate.timetable.academic_year_id = edumate.view_student_class_enrolment.academic_year_id
where
current date <= edumate.student_form_run.end_date
and
current date <= edumate.view_student_class_enrolment.end_date
and
current date >= (
	case
	when edumate.student_form_run.start_date_locked=1 then edumate.timetable.computed_v_start_date
	else edumate.student_form_run.start_date
	end
)
and
current date >= (
	case
	when edumate.view_student_class_enrolment.start_date_locked=1 then edumate.timetable.computed_v_start_date
	else edumate.view_student_class_enrolment.start_date
	end
)
;

CREATE OR REPLACE VIEW SCHOOLBOX_TEACHERCLASS AS
select
edumate.staff.staff_number,
edumate.course.code||'.'||edumate.class.identifier AS "CLASS_CODE",
'Cover' as "CLASS_TYPE"
from (

	select distinct
	edumate.term.timetable_id,
	edumate.cycle.cycle_id,
	edumate.getdayindex(edumate.term.start_date, edumate.term.cycle_start_day, edumate.cycle.days_in_cycle, current date) AS "DAY_INDEX"
	from edumate.term
	inner join edumate.term_group on edumate.term_group.term_id = edumate.term.term_id
	and edumate.term.start_date <= current date and edumate.term.end_date >= current date
	inner join edumate.cycle on edumate.cycle.cycle_id = edumate.term_group.cycle_id
	inner join edumate.timetable on edumate.timetable.timetable_id=edumate.term.timetable_id

) current_day_index
inner join edumate.cycle_day on edumate.cycle_day.cycle_id = current_day_index.cycle_id and edumate.cycle_day.day_index = current_day_index.day_index
inner join edumate.period_cycle_day on edumate.period_cycle_day.cycle_day_id = edumate.cycle_day.cycle_day_id
inner join edumate.period on edumate.period.period_id = edumate.period_cycle_day.period_id
inner join edumate.period_class on edumate.period_class.timetable_id = current_day_index.timetable_id
and edumate.period_class.period_cycle_day_id = edumate.period_cycle_day.period_cycle_day_id
and edumate.period_class.effective_start <= current date and edumate.period_class.effective_end >= current date
inner join edumate.class on edumate.class.class_id = edumate.period_class.class_id
inner join edumate.course on edumate.course.course_id = edumate.class.course_id
left join edumate.perd_cls_replace on edumate.perd_cls_replace.period_class_id = edumate.period_class.period_class_id
and edumate.perd_cls_replace.from_date <= current date and edumate.perd_cls_replace.to_date >= current date
inner join edumate.staff on edumate.staff.staff_id=edumate.perd_cls_replace.staff_id

union

select distinct
edumate.staff.staff_number,
edumate.course.code||'.'||edumate.class.identifier AS "CLASS_CODE",
edumate.class_type.class_type
from edumate.view_student_class_enrolment
inner join edumate.class on edumate.class.class_id = edumate.view_student_class_enrolment.class_id
inner join edumate.class_type on edumate.class.class_type_id = edumate.class_type.class_type_id
inner join edumate.course on edumate.course.course_id = edumate.class.course_id
inner join edumate.class_teacher on edumate.class_teacher.class_id = edumate.view_student_class_enrolment.class_id
inner join edumate.teacher on edumate.teacher.teacher_id = edumate.class_teacher.teacher_id
inner join edumate.staff on edumate.staff.contact_id = edumate.teacher.contact_id
inner join edumate.student_form_run on edumate.student_form_run.student_id = edumate.view_student_class_enrolment.student_id
inner join edumate.form_run on edumate.form_run.form_run_id = edumate.student_form_run.form_run_id
inner join edumate.timetable on edumate.timetable.timetable_id = edumate.form_run.timetable_id
and edumate.timetable.academic_year_id = edumate.view_student_class_enrolment.academic_year_id
where current date <= edumate.student_form_run.end_date
and current date <= edumate.view_student_class_enrolment.end_date
and current date >= (
	case
	when edumate.student_form_run.start_date_locked=1 then edumate.timetable.computed_v_start_date
	else edumate.student_form_run.start_date
	end
)
and current date >= (
	case
	when edumate.view_student_class_enrolment.start_date_locked=1 then edumate.timetable.computed_v_start_date
	else edumate.view_student_class_enrolment.start_date
	end
)
;
