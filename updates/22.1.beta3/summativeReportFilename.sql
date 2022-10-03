alter table summative_report
    add file_name varchar(255) default "{external_id}/Semester Report - {firstname} {lastname}" null;

