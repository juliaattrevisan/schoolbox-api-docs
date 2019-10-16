CREATE OR REPLACE VIEW SCHOOLBOX_STAFF AS
select
edumate.staff.staff_number,
edumate.salutation.salutation,
coalesce(replace(edumate.contact.preferred_name,'&#0'||'39;',''''), replace(edumate.contact.firstname,'&#0'||'39;','''')) as firstname,
replace(edumate.contact.surname,'&#039;','''') as surname,
edumate.sys_user.username,
edumate.contact.email_address,
edumate.house.house,
edumate.campus.campus,
replace(edumate.work_detail.title,'&#039;','''') as title
from edumate.staff
inner join edumate.contact on edumate.contact.contact_id = edumate.staff.contact_id
inner join edumate.staff_employment on edumate.staff_employment.staff_id = edumate.staff.staff_id
inner join edumate.work_detail on edumate.work_detail.contact_id=edumate.contact.contact_id
inner join edumate.salutation on edumate.salutation.salutation_id = edumate.contact.salutation_id
left join edumate.sys_user on edumate.sys_user.contact_id = edumate.contact.contact_id
left join edumate.house on edumate.house.house_id = edumate.staff.house_id
left join edumate.campus on edumate.campus.campus_id = edumate.staff.campus_id
where (edumate.staff_employment.end_date is null or edumate.staff_employment.end_date >= current date)
and edumate.staff_employment.start_date <= current date
and (edumate.contact.pronounced_name is null or edumate.contact.pronounced_name != 'NOT STAFF')
;
