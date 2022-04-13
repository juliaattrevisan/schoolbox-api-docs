alter table pastoral_access
    add _role_id int null;

alter table pastoral_access
    add _role_type int null;


update pastoral_access
    join `groups` on pastoral_access.role_id = `groups`.id
set
    _role_id = role_id,
    _role_type = role_type,
    role_type = 'parent',
    role_id = null
where `groups`.group_type = 'parent';


alter table pastoral_audience
    add _role_id int null;

alter table pastoral_audience
    add _role_type int null;

update pastoral_audience
    join `groups` on pastoral_audience.role_id = `groups`.id
set
    _role_id = role_id,
    _role_type = role_type,
    role_type = 'parent',
    role_id = null
where `groups`.group_type = 'parent';
