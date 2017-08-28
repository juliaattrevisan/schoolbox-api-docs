INSERT INTO assessment_course_unit_specification (assessment_id, course_unit_specification_id)
select sb.assessment_id, cus.id from schoolbox.submission_box sb
join assessment ass on ass.id = sb.assessment_id
left join assessment_course_unit_specification acus on acus.assessment_id = ass.id
left join assessment_course_unit_implementation acut ON acut.assessment_id = ass.id
left join assessment_folder af ON af.assessment_id = ass.id
inner join course_unit_specification cus ON cus.folder_id = sb.category
where acus.assessment_id is null and acut.assessment_id is null and af.assessment_id is null;