-- set the event source for all events to what it should be

-- resource booking events, should be 'booking'
update event
set variant = 'booking'
where id in (select event_id from resource_booking);
