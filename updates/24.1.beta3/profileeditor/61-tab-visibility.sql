-- This removes any tab visibility relationships from the db
-- that would otherwise have allowed Students to see content
-- in other Students' tabs. This shouldn't be needed, but in
-- the off-chance that someone's already messed with this
-- setting in the beta period and could produce anomalies.

DELETE FROM profile_tab_roletype_visibility
WHERE roletype = 'student';

-- TabSettingsTool does not include any Student visibilties by default
-- so no changes required there.