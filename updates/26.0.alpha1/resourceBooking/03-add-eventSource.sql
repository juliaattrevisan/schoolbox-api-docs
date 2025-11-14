-- add new column to events table to identify where the event came from

alter table event
    modify variant enum ('event', 'task', 'booking') default 'event' not null;

