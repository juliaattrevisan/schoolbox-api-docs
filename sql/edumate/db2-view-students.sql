CREATE OR REPLACE VIEW SCHOOLBOX_STUDENTS AS
WITH student_single_form AS (
SELECT
ROW_NUMBER() OVER
(
PARTITION BY edumate.student_form_run.student_id
ORDER BY
(
CASE WHEN edumate.student_form_run.start_date_locked = 1
THEN edumate.timetable.computed_v_start_date
ELSE edumate.student_form_run.start_date
END
) DESC,
edumate.form_run.form_run DESC
) AS FORM_NUM,
edumate.student_form_run.student_id,
edumate.form.form,
edumate.form_run.form_run,
replace(edumate.contact.firstname,'&#039;','''') AS FIRSTNAME,
replace(edumate.contact.surname,'&#039;','''') AS SURNAME,
COALESCE(edumate.salutation.salutation||CHR(32),'')||LEFT(replace(coordinator.firstname,'&amp;#039;',''''),1)||'. '||replace(coordinator.surname,'&amp;#039;','''') AS FORM_STAFF_NAME,
CASE
WHEN edumate.student_form_run.start_date_locked = 1
THEN edumate.timetable.computed_v_start_date
ELSE edumate.student_form_run.start_date
END AS START_DATE
FROM edumate.student_form_run
INNER JOIN edumate.student ON edumate.student.student_id = edumate.student_form_run.student_id
INNER JOIN edumate.contact ON edumate.contact.contact_id = edumate.student.contact_id
INNER JOIN edumate.form_run ON edumate.form_run.form_run_id = edumate.student_form_run.form_run_id
INNER JOIN edumate.form ON edumate.form.form_id = edumate.form_run.form_id
INNER JOIN edumate.timetable ON edumate.timetable.timetable_id = edumate.form_run.timetable_id
LEFT JOIN edumate.staff ON edumate.staff.staff_id = (
CASE WHEN edumate.contact.gender_id = 3 THEN edumate.form_run.fcoordinator_id
ELSE edumate.form_run.coordinator_id
END)
LEFT JOIN edumate.contact coordinator ON coordinator.contact_id = edumate.staff.contact_id
LEFT JOIN edumate.salutation ON edumate.salutation.salutation_id = coordinator.salutation_id AND edumate.salutation.salutation != 'Unspecified'
WHERE
CURRENT_DATE BETWEEN (
CASE WHEN edumate.student_form_run.start_date_locked = 1 THEN edumate.timetable.computed_v_start_date
ELSE edumate.student_form_run.start_date
END)
AND edumate.student_form_run.end_date
),

future_student_single_form AS (
SELECT
ROW_NUMBER() OVER
(
PARTITION BY edumate.student_form_run.student_id
ORDER BY
(
CASE WHEN edumate.student_form_run.start_date_locked = 1 THEN edumate.timetable.computed_v_start_date
ELSE edumate.student_form_run.start_date
END) DESC,
edumate.form_run.form_run DESC
) AS FORM_NUM,
edumate.student_form_run.student_id,
edumate.form.form,
edumate.form_run.form_run,
replace(edumate.contact.firstname,'&amp;#039;','''') AS FIRSTNAME,
replace(edumate.contact.surname,'&amp;#039;','''') AS SURNAME,
COALESCE(edumate.salutation.salutation||CHR(32),'')||LEFT(replace(coordinator.firstname,'&amp;#039;',''''),1)||'. '||replace(coordinator.surname,'&amp;#039;','''') AS FORM_STAFF_NAME,
CASE
WHEN edumate.student_form_run.start_date_locked = 1 THEN edumate.timetable.computed_v_start_date
ELSE edumate.student_form_run.start_date
END AS START_DATE
FROM edumate.stu_enrolment
INNER JOIN edumate.student ON edumate.stu_enrolment.student_id = edumate.student.student_id
INNER JOIN edumate.contact ON edumate.student.contact_id = edumate.contact.contact_id
INNER JOIN edumate.form_run ON edumate.form_run.form_run_id = edumate.stu_enrolment.exp_start_form
INNER JOIN edumate.form ON edumate.form.form_id = edumate.form_run.form_id
INNER JOIN edumate.timetable ON edumate.timetable.timetable_id = edumate.form_run.timetable_id
INNER JOIN edumate.academic_year ON edumate.academic_year.academic_year_id = edumate.timetable.academic_year_id
INNER JOIN edumate.student_form_run ON edumate.form_run.form_run_id = edumate.student_form_run.form_run_id AND edumate.student.student_id = edumate.student_form_run.student_id
LEFT JOIN edumate.staff ON edumate.staff.staff_id = (
CASE WHEN edumate.contact.gender_id = 3 THEN edumate.form_run.fcoordinator_id
ELSE edumate.form_run.coordinator_id
END)
LEFT JOIN edumate.contact coordinator ON coordinator.contact_id = edumate.staff.contact_id
LEFT JOIN edumate.salutation ON edumate.salutation.salutation_id = coordinator.salutation_id AND edumate.salutation.salutation != 'Unspecified'
WHERE
edumate.academic_year.academic_year > char(year(CURRENT date))
and edumate.stu_enrolment.student_id not in (select student_id FROM edumate.student_form_run where current date between start_date and end_date)
),

student_tutors AS (
SELECT
student_single_form.student_id,
TRIM(SUBSTR(XMLCAST(XMLGROUP(', '||COALESCE(edumate.salutation.salutation||CHR(32),'')||LEFT(edumate.contact.firstname,1)||'. '||edumate.contact.surname AS a
ORDER BY edumate.class_teacher.is_primary DESC) AS VARCHAR(1024)),3)) AS TUTOR_NAME
FROM student_single_form
INNER JOIN edumate.view_student_class_enrolment ON edumate.view_student_class_enrolment.student_id = student_single_form.student_id
AND edumate.view_student_class_enrolment.class_type_id = 2
AND CURRENT_DATE BETWEEN (
CASE
WHEN edumate.view_student_class_enrolment.start_date_locked = 1 THEN student_single_form.start_date
ELSE edumate.view_student_class_enrolment.start_date
END)
AND edumate.view_student_class_enrolment.end_date
INNER JOIN edumate.class_teacher ON edumate.class_teacher.class_id = edumate.view_student_class_enrolment.class_id
INNER JOIN edumate.teacher ON edumate.teacher.teacher_id = edumate.class_teacher.teacher_id
INNER JOIN edumate.contact ON edumate.contact.contact_id = edumate.teacher.contact_id
LEFT JOIN edumate.salutation ON edumate.salutation.salutation_id = edumate.contact.salutation_id
AND edumate.salutation.salutation != 'Unspecified'
WHERE student_single_form.form_num = 1
GROUP BY student_single_form.student_id
),

future_student_tutors AS (
SELECT
future_student_single_form.student_id,
'Unspecified' AS TUTOR_NAME
FROM future_student_single_form
WHERE future_student_single_form.form_num = 1
GROUP BY future_student_single_form.student_id
),

student_house_info AS (
SELECT
student_single_form.student_id,
edumate.house.house,
TRIM(SUBSTR(XMLCAST(XMLGROUP(', '||COALESCE(edumate.salutation.salutation||CHR(32),'')||LEFT(edumate.contact.firstname,1)||'. '||edumate.contact.surname AS a) AS VARCHAR(1024)),3)) AS HEAD_OF_HOUSE
FROM student_single_form
INNER JOIN edumate.student ON edumate.student.student_id = student_single_form.student_id
LEFT JOIN edumate.house ON edumate.house.house_id = edumate.student.house_id
LEFT JOIN edumate.house_head ON edumate.house_head.house_id = edumate.house.house_id AND CURRENT_DATE BETWEEN effective_start AND COALESCE(effective_end,CURRENT_DATE)
LEFT JOIN edumate.staff ON edumate.staff.staff_id = edumate.house_head.staff_id
LEFT JOIN edumate.contact ON edumate.contact.contact_id = edumate.staff.contact_id
LEFT JOIN edumate.salutation ON edumate.salutation.salutation_id = edumate.contact.salutation_id
AND edumate.salutation.salutation != 'Unspecified'
GROUP BY student_single_form.student_id, edumate.house.house
),

future_student_house_info AS (
SELECT
future_student_single_form.student_id,
edumate.house.house,
TRIM(SUBSTR(XMLCAST(XMLGROUP(', '||COALESCE(edumate.salutation.salutation||CHR(32),'')||LEFT(edumate.contact.firstname,1)||'. '||edumate.contact.surname AS a) AS VARCHAR(1024)),3)) AS HEAD_OF_HOUSE
FROM future_student_single_form
INNER JOIN edumate.student ON edumate.student.student_id = future_student_single_form.student_id
LEFT JOIN edumate.house ON edumate.house.house_id = edumate.student.house_id
LEFT JOIN edumate.house_head ON edumate.house_head.house_id = edumate.house.house_id
LEFT JOIN edumate.staff ON edumate.staff.staff_id = edumate.house_head.staff_id
LEFT JOIN edumate.contact ON edumate.contact.contact_id = edumate.staff.contact_id
LEFT JOIN edumate.salutation ON edumate.salutation.salutation_id = edumate.contact.salutation_id
AND edumate.salutation.salutation != 'Unspecified'
GROUP BY future_student_single_form.student_id, edumate.house.house
)

SELECT
edumate.student.student_number,
student_single_form.firstname,
student_single_form.surname,
edumate.contact.birthdate,
student_house_info.house,
student_single_form.form as "YEAR_LEVEL",
student_tutors.tutor_name,
student_house_info.head_of_house,
student_single_form.form_run as "FORM_NAME",
student_single_form.form_staff_name,
edumate.sys_user.username,
edumate.contact.email_address
FROM student_single_form
INNER JOIN edumate.student ON edumate.student.student_id = student_single_form.student_id
INNER JOIN edumate.contact ON edumate.contact.contact_id = edumate.student.contact_id
INNER JOIN edumate.sys_user ON edumate.contact.contact_id = edumate.sys_user.contact_id
LEFT JOIN student_house_info ON student_house_info.student_id = edumate.student.student_id
LEFT JOIN student_tutors ON student_tutors.student_id = edumate.student.student_id
UNION
SELECT
edumate.student.student_number,
future_student_single_form.firstname,
future_student_single_form.surname,
edumate.contact.birthdate,
future_student_house_info.house,
future_student_single_form.form as "YEAR_LEVEL",
future_student_tutors.tutor_name,
future_student_house_info.head_of_house,
future_student_single_form.form_run as "FORM_NAME",
future_student_single_form.form_staff_name,
edumate.sys_user.username,
edumate.contact.email_address
FROM future_student_single_form
INNER JOIN edumate.student ON edumate.student.student_id = future_student_single_form.student_id
INNER JOIN edumate.contact ON edumate.contact.contact_id = edumate.student.contact_id
LEFT JOIN edumate.sys_user ON edumate.contact.contact_id = edumate.sys_user.contact_id
LEFT JOIN future_student_house_info ON future_student_house_info.student_id = edumate.student.student_id
LEFT JOIN future_student_tutors ON future_student_tutors.student_id = edumate.student.student_id
;


