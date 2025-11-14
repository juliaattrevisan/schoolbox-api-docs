-- add new column to events table to identify where the event came from

alter table event
    add source enum ('calendar', 'booking') null after variant;
