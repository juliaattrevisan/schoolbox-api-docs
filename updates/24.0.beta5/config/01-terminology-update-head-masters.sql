UPDATE config
SET
    title = 'Allow Email Head of House from Class List',
    description = 'If toggled to ''ON'', allows staff to email head of house of a class.'
WHERE name = 'email_housemasters';
