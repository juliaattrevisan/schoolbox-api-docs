# SA-19408: Update pastoral severity config title to remove trailing period
# Update the config title from "Allow specification of severity on pastoral records." to "Allow specification of severity on pastoral records"

UPDATE config 
SET title = 'Allow specification of severity on pastoral records'
WHERE title = 'Allow specification of severity on pastoral records.';