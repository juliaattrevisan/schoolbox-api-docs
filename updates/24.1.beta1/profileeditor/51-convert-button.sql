-- Synweb was previously a hardcoded template button,
-- then became system button,
-- and evolves yet again into a generic custom button for students.
-- My program design makes this relatively painless afaict lol

UPDATE profile_button
SET type = 'custom'
WHERE type = 'pastoralsynergetic'
LIMIT 1;
