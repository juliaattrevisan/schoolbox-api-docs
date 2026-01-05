-- Update the options and options_default to be NULL
-- We are going to open the DP in the modal like we do in other places instead
UPDATE `profile_field`
SET
    options = NULL,
    options_default = NULL
WHERE
    field = 'differentiationProfile'
    AND roletype = 'student';
