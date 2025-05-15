# create new sequence column
alter table courseTemplates
    add sequence int not null after unitTemplateId;

# init sequence column to the ID of the row...
UPDATE courseTemplates set sequence = id;