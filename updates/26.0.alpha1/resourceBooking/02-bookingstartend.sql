## resource booking may not start/end at the same time as the event... this will assist in tracking that
alter table resource_booking
add start_datetime datetime null after deleted_at;

alter table resource_booking
add end_datetime datetime null after start_datetime;

