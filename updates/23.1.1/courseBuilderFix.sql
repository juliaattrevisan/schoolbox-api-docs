alter table courseTemplates
    drop foreign key IF EXISTS courseTemplates_homepageTemplate_id_fk;

alter table courseTemplates
    drop foreign key IF EXISTS unitTemplates_homepageTemplate_id_fk2;

