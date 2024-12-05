# Clean up any existing bulk import accesses which somehow relate to pastoral
# records?
# I think it's extremely unlikely they could exist, but clean up anyway.
delete pbia
from pastoral_bulk_import_access pbia
left join pastoral_bulk_import pbi on pbia.pastoral_bulk_import_id = pbi.id
where pbi.id is null;

# Then drop and recreate the foreign key the way it should be created.
alter table pastoral_bulk_import_access
    drop foreign key fk_pastoral_bulk_import_access_pastoral_bulk_import_id;
alter table pastoral_bulk_import_access
    add constraint fk_pastoral_bulk_import_access_pastoral_bulk_import_id
        foreign key (pastoral_bulk_import_id) references pastoral_bulk_import (id);
