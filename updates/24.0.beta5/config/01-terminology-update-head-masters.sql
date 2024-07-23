UPDATE config
SET
    title = 'Allow Email Head of Houses from Class List',
    description = 'If toggled to ''ON'', allows staff to email head of houses of a class.'
WHERE name = 'email_housemasters';
