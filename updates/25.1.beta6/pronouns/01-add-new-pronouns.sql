-- Add new possessive_adjective pronoun option
ALTER TABLE pronoun
ADD COLUMN possessive_adjective VARCHAR(250) DEFAULT 'their' AFTER possessive;

-- Add new reflexive pronoun option
ALTER TABLE pronoun
ADD COLUMN reflexive VARCHAR(250) DEFAULT 'themselves' AFTER possessive_adjective;

-- Add new pronouns to existing entries
UPDATE pronoun
SET
    possessive_adjective = 'his',
    reflexive = 'himself'
WHERE
    subjective = 'he';

UPDATE pronoun
SET
    possessive_adjective = 'her',
    reflexive = 'herself'
WHERE
    subjective = 'she';

UPDATE pronoun
SET
    possessive_adjective = 'their',
    reflexive = 'themselves'
WHERE
    subjective = 'they';
