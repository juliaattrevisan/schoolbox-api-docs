-- Create some terms for this year only.
INSERT INTO `term` (
    `title`,
    `start`,
    `finish`,
    `archived`
) VALUES (
    DATE_FORMAT(NOW(), 'Semester 1, %Y'),
    DATE_FORMAT(NOW(), '%Y-01-01'),
    DATE_FORMAT(NOW(), '%Y-06-30'),
    FALSE
), (
    DATE_FORMAT(NOW(), 'Semester 2, %Y'),
    DATE_FORMAT(NOW(), '%Y-07-01'),
    DATE_FORMAT(NOW(), '%Y-12-31'),
    FALSE
);
