-- set the event source for all events to what it should be

-- resource booking events, should be 'booking'
update event
set source = 'booking'
where id in (select event_id from schoolbox.resource_booking);

-- all other events should be 'calendar'
update event
set source = 'calendar'
where source is null;
