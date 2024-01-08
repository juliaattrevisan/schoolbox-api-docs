alter table courseTemplates
    drop foreign key IF EXISTS courseTemplates_homepageTemplate_id_fk;

alter table courseTemplates
    drop foreign key IF EXISTS unitTemplates_homepageTemplate_id_fk2;

alter table courseTemplates
    modify courseTemplateId int not null;

alter table courseTemplates
    modify unitTemplateId int not null;

