-- Add new possessive_adjective pronoun option
ALTER TABLE pronoun
ADD COLUMN possessive_adjective VARCHAR(250) DEFAULT 'their' NOT NULL AFTER possessive;

-- Add new reflexive pronoun option
ALTER TABLE pronoun
ADD COLUMN reflexive VARCHAR(250) DEFAULT 'themselves' NOT NULL AFTER possessive_adjective;

-- Add new pronouns to existing entries
UPDATE pronoun SET possessive_adjective = 'his' WHERE id = 1;

UPDATE pronoun SET reflexive = 'himself' WHERE id = 1;

UPDATE pronoun SET possessive_adjective = 'her' WHERE id = 2;

UPDATE pronoun SET reflexive = 'herself' WHERE id = 2;

UPDATE pronoun SET possessive_adjective = 'their' WHERE id = 3;

UPDATE pronoun SET reflexive = 'themselves' WHERE id = 3;
