UPDATE event e
INNER JOIN luEventType et on e.type = et.eventTypeID
SET e.class = 'task'
WHERE et.eventType = 'Task';
