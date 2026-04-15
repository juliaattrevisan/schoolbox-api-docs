-- Create lu_nationality table to store the list of nationalities
CREATE TABLE lu_nationality
(
    id   INT(11) auto_increment PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

INSERT INTO lu_nationality (name)
VALUES
    ('Australian'),
    ('Japanese'),
    ('Spanish'),
    ('Chinese');
