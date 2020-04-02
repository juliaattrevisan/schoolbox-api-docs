UPDATE event e
INNER JOIN luEventType et on e.type = et.eventTypeID
SET e.variant = 'task'
WHERE et.eventType = 'Task';
