# This is required to avoid an error when duplicating reports with long names.
# The duplication process appends ' (copy)' to the name of the newly created report, and so if the old name was near
# the length limit (previously 100 characters), the new name can exceed that limit.
# To solve that problem we still enforce the 100 character limit when creating or modifying a report, but we now allow
# 255 characters to be saved when an existing report is duplicated.
ALTER TABLE summative_report
CHANGE COLUMN title title varchar(255) NOT NULL;
