UPDATE config
SET
    description = "If toggled to ON, the View Photos button will appear on a student's profile for parents, students and staff to view photos of the student that have been uploaded to GeoSnapShot. NOTE: You must have a valid API key."
WHERE
    name = "geosnapshot_enabled";
