#deprecate scheduleq
alter table scheduleq
    change id_hash _id_hash varchar(40) not null;
alter table scheduleq
    change identifier _identifier blob not null;
alter table scheduleq
    change command _command varchar(32) null;
alter table scheduleq
    change data _data blob not null;

rename table scheduleq to _scheduleq;
