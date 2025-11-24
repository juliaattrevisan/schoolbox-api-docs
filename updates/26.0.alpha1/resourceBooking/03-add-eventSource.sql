-- add new column to events table to identify where the event came from

-- new option booking (events created from resource bookings)
-- existing options: event (normal events), task (which is a to-do list)
alter table event
    modify variant enum ('event', 'task', 'booking') default 'event' not null;

