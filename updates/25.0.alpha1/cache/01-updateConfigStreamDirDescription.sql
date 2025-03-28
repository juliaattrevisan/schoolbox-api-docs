UPDATE config
SET description = 'Cache folder for files which Schoolbox may need to write to disk, in order to stream their content to another process.'
WHERE name = 'stream_dir';
