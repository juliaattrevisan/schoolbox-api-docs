# SA-19408: Update pastoral severity config title to remove trailing period
# Update the config title from "Allow specification of severity on pastoral records." to "Allow specification of severity on pastoral records"

UPDATE config 
SET title = 'Allow specification of severity on pastoral records'
WHERE name = 'pastoral_severity';

# SA-19408: Update similarity check config title
# Update the config title to "Allow Students and Parents to access the Similarity Check report"

UPDATE config 
SET title = 'Allow Students and Parents to access the Similarity Check report'
WHERE name = 'similarity_check_allow_students_report_access';