# update table to have both Mobile, and Device
alter table msg_reroute
    modify delivery set ('MU', 'DI', 'IN', 'MO', 'DE', 'EM') null;

# update entries to change MO to DE
UPDATE msg_reroute set delivery = replace(delivery, 'MO', 'DE');

# update table to have only Device (remove Mobile
alter table msg_reroute
    modify delivery set ('MU', 'DI', 'IN', 'DE', 'EM') null;

# update table to have both Mobile, and Device
alter table msg_route_perm
    modify mask set ('MU', 'DI', 'IN', 'MO', 'DE', 'EM') null;

alter table msg_route_perm
    modify role_default set ('MU', 'DI', 'IN', 'MO', 'DE', 'EM') null;

# update entries to change MO to DE
UPDATE msg_route_perm set mask = replace(mask, 'MO', 'DE'),
                          role_default = replace(role_default, 'MO', 'DE');

# update table to have only Device (remove Mobile
alter table msg_route_perm
    modify mask set ('MU', 'DI', 'IN', 'DE', 'EM') null;

alter table msg_route_perm
    modify role_default set ('MU', 'DI', 'IN', 'DE', 'EM') null;