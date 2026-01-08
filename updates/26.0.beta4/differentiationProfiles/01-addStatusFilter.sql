-- Inserting differentiation profile filters for status
INSERT INTO filter (entity, class)
VALUES
    (
        'Schoolbox\\DifferentiationProfile\\Mapper',
        'Schoolbox\\Filter\\Filters\\DifferentiationProfile\\Status\\Definition'
    );
