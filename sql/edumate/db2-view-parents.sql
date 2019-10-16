CREATE OR REPLACE VIEW SCHOOLBOX_PARENT_STUDENT AS
SELECT
        edumate.student.student_number,
        edumate.carer.carer_number
FROM table(edumate.get_currently_enroled_students(current_date))
INNER JOIN edumate.student ON edumate.student.student_id = edumate.get_currently_enroled_students.student_id
INNER JOIN edumate.view_student_mail_carers ON edumate.view_student_mail_carers.student_id = edumate.student.student_id
INNER JOIN edumate.contact ON
        edumate.contact.contact_id = edumate.view_student_mail_carers.carer1_contact_id
        OR edumate.contact.contact_id = edumate.view_student_mail_carers.carer2_contact_id
        OR edumate.contact.contact_id = edumate.view_student_mail_carers.carer3_contact_id
        OR edumate.contact.contact_id = edumate.view_student_mail_carers.carer4_contact_id
INNER JOIN edumate.carer ON edumate.carer.contact_id = edumate.contact.contact_id
UNION
SELECT
        edumate.student.student_number,
        edumate.carer.carer_number
FROM
(
        SELECT
                edumate.student.student_id
        FROM edumate.stu_enrolment
        INNER JOIN edumate.student ON edumate.stu_enrolment.student_id = edumate.student.student_id
        INNER JOIN edumate.contact ON edumate.student.contact_id = edumate.contact.contact_id
        INNER JOIN edumate.form_run ON edumate.stu_enrolment.exp_start_form = edumate.form_run.form_run_id
        INNER JOIN edumate.timetable ON edumate.form_run.timetable_id = edumate.timetable.timetable_id
        INNER JOIN edumate.academic_year ON edumate.academic_year.academic_year_id = edumate.timetable.academic_year_id
        INNER JOIN edumate.student_form_run ON edumate.form_run.form_run_id = edumate.student_form_run.form_run_id
                AND edumate.student.student_id = edumate.student_form_run.student_id
        WHERE edumate.academic_year.academic_year > char(year(current date))
) asd
INNER JOIN edumate.student ON edumate.student.student_id = asd.student_id
INNER JOIN edumate.view_student_mail_carers ON edumate.view_student_mail_carers.student_id = edumate.student.student_id
INNER JOIN edumate.contact ON
        edumate.contact.contact_id = edumate.view_student_mail_carers.carer1_contact_id
        OR edumate.contact.contact_id = edumate.view_student_mail_carers.carer2_contact_id
        OR edumate.contact.contact_id = edumate.view_student_mail_carers.carer3_contact_id
        OR edumate.contact.contact_id = edumate.view_student_mail_carers.carer4_contact_id
INNER JOIN edumate.carer ON edumate.carer.contact_id = edumate.contact.contact_id
;

CREATE OR REPLACE VIEW SCHOOLBOX_PARENTS AS
SELECT DISTINCT
car1.carer_number,
replace(c1.firstname, '&#039;','''')||CHR(32)||replace(c1.surname, '&#039;','''') AS FULL_NAME,
replace(c1.email_address, '&#039;','''') AS EMAIL,
su1.username as USERNAME,
c1.mobile_phone AS MOBILE,
car2.carer_number AS SPOUSE_CARER_NUMBER,
replace(c2.firstname, '&#039;','''')||CHR(32)||replace(c2.surname, '&#039;','''') AS SPOUSE_FULL_NAME,
replace(c2.email_address, '&#039;','''') AS SPOUSE_EMAIL,
su2.username as SPOUSE_USERNAME,
c2.mobile_phone AS SPOUSE_MOBILE,
case
when c1.email_address like c2.email_address then replace(c1.email_address, '&#039;','''')
when c1.email_address is null and c2.email_address is null then ''
when c1.email_address is not null and c2.email_address is null then replace(c1.email_address, '&#039;','''')
when c1.email_address is null and c2.email_address is not null then replace(c2.email_address, '&#039;','''')
when c1.email_address is not null and c2.email_address is not null then replace(c1.email_address, '&#039;','''') || ',' || replace(c2.email_address, '&#039;','''')
end as COMBINED_EMAIL_ADDRESS
FROM table(edumate.get_currently_enroled_students(current_date))
INNER JOIN edumate.student ON edumate.student.student_id = edumate.get_currently_enroled_students.student_id
INNER JOIN edumate.view_student_mail_carers ON edumate.view_student_mail_carers.student_id = edumate.student.student_id
INNER JOIN edumate.contact c1 ON c1.contact_id = edumate.view_student_mail_carers.carer1_contact_id
INNER JOIN edumate.carer car1 ON car1.contact_id = c1.contact_id
LEFT JOIN edumate.contact c2 ON c2.contact_id = edumate.view_student_mail_carers.carer2_contact_id
LEFT JOIN edumate.carer car2 ON car2.contact_id = c2.contact_id
LEFT JOIN sys_user su1 ON su1.contact_id = c1.contact_id
LEFT JOIN sys_user su2 ON su2.contact_id = c2.contact_id
UNION
SELECT DISTINCT
car1.carer_number,
replace(c1.firstname, '&#039;','''')||CHR(32)||replace(c1.surname, '&#039;','''') AS FULL_NAME,
replace(c1.email_address, '&#039;','''') AS EMAIL,
su1.username as USERNAME,
c1.mobile_phone AS MOBILE,
car2.carer_number AS SPOUSE_CARER_NUMBER,
replace(c2.firstname, '&#039;','''')||CHR(32)||replace(c2.surname, '&#039;','''') AS SPOUSE_FULL_NAME,
replace(c2.email_address, '&#039;','''') AS SPOUSE_EMAIL,
su2.username as SPOUSE_USERNAME,
c2.mobile_phone AS SPOUSE_MOBILE,
case when c1.email_address like c2.email_address then replace(c1.email_address, '&#039;','''')
when c1.email_address is null and c2.email_address is null then ''
when c1.email_address is not null and c2.email_address is null then replace(c1.email_address, '&#039;','''')
when c1.email_address is null and c2.email_address is not null then replace(c2.email_address, '&#039;','''')
when c1.email_address is not null and c2.email_address is not null then replace(c1.email_address, '&#039;','''') || ',' || replace(c2.email_address, '&#039;','''')
end as COMBINED_EMAIL_ADDRESS
FROM (
SELECT edumate.student.student_id
FROM edumate.stu_enrolment
INNER JOIN edumate.student ON edumate.stu_enrolment.student_id = edumate.student.student_id
INNER JOIN edumate.contact ON edumate.student.contact_id = edumate.contact.contact_id
INNER JOIN edumate.form_run ON edumate.stu_enrolment.exp_start_form = edumate.form_run.form_run_id
INNER JOIN edumate.timetable ON edumate.form_run.timetable_id = edumate.timetable.timetable_id
INNER JOIN edumate.academic_year ON edumate.academic_year.academic_year_id = edumate.timetable.academic_year_id
INNER JOIN edumate.student_form_run ON edumate.form_run.form_run_id = edumate.student_form_run.form_run_id AND edumate.student.student_id = edumate.student_form_run.student_id
WHERE edumate.academic_year.academic_year > char(year(current date))
) asd
INNER JOIN edumate.student ON edumate.student.student_id = asd.student_id
INNER JOIN edumate.view_student_mail_carers ON edumate.view_student_mail_carers.student_id = edumate.student.student_id
INNER JOIN edumate.contact c1 ON c1.contact_id = edumate.view_student_mail_carers.carer1_contact_id
INNER JOIN edumate.carer car1 ON car1.contact_id = c1.contact_id
LEFT JOIN edumate.contact c2 ON c2.contact_id = edumate.view_student_mail_carers.carer2_contact_id
LEFT JOIN edumate.carer car2 ON car2.contact_id = c2.contact_id;
LEFT JOIN sys_user su1 ON su1.contact_id = c1.contact_id
LEFT JOIN sys_user su2 ON su2.contact_id = c2.contact_id
;
