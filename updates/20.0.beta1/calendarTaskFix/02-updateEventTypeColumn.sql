UPDATE event e
INNER JOIN luEventType et on e.type = et.eventTypeID
SET e.event_type = 'task'
WHERE et.eventType = 'Task';
