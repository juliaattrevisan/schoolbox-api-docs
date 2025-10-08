-- Remove the inline/external detection for each mime type
-- by deprecating the column first

alter table allowedFileTypes
    change inline _inline enum ('true', 'false') null;
