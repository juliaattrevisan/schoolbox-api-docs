alter table userAccess
    add favourite bool default false null;

UPDATE menu_top
SET url = '/groups/mygroups'
WHERE url = '/resource/mygroups';

UPDATE menu_side
SET url = '/groups/mygroups'
WHERE url = '/group/join';
