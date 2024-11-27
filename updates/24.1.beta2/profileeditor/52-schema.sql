-- (roletype, field) is no longer a unique index: custom works
-- required the existence of multiple (roletype, field) tuples
-- that are the same, because we represent the same data differently

drop index roletype_field on profile_field;

create index roletype_field
    on profile_field (roletype, field);

