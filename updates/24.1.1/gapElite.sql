#ensure gap alaysis is enabled by default for elite schools

update config
set editable = (IF((SELECT * FROM (SELECT COUNT(*) FROM config WHERE name = 'site_plan' AND value = 'elite') t1), 'true', 'false')),
    value = (IF((SELECT * FROM (SELECT COUNT(*) FROM config WHERE name = 'site_plan' AND value = 'elite') t1), '1', '0'))
where name = 'curriculum_coverage_report_enabled';