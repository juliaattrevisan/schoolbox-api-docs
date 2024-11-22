-- Synweb was previously a hardcoded template button,
-- then became system button,
-- and evolves yet again into a generic custom button for students.
-- My program design makes this relatively painless afaict lol

UPDATE profile_button
SET type = 'custom'
WHERE type = 'pastoralsynergetic'
LIMIT 1;

-- If anyone was already migrated, remove the SELF visibility
-- Thanks Mr Sannang

DELETE ptr
FROM profile_tab pt
INNER JOIN profile_tab_roletype_visibility ptr ON pt.id = ptr.profile_tab_id
WHERE pt.tab = 'contacts'
AND ptr.roletype = 'self';
