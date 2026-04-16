-- Create lu_language table to store the list of nationalities
CREATE TABLE lu_language
(
    id   INT(11) auto_increment PRIMARY KEY,
    code VARCHAR(3) NOT NULL,
    name VARCHAR(255) NOT NULL
);

INSERT INTO lu_language (code, name)
VALUES
    ('JAP', 'Japanese'),
    ('ESP', 'Spanish'),
    ('CHN', 'Chinese');
