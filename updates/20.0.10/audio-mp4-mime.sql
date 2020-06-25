-- audio/m4a is a nonstandard mime type according to nerds at
-- https://stackoverflow.com/questions/39885749/m4a-is-considered-audio-m4a-or-audio-mp4

UPDATE allowedFileTypes
SET mime = 'audio/mp4'
WHERE mime = 'audio/m4a';
