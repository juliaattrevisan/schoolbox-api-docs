-- These configs are no longer needed, so they can be removed
DELETE FROM config WHERE name IN (
#     'file_dir', -- can't remove just yet
    'myfile_dir',
    'submission_dir',
    'returns_dir',
    'dropbox_dir',
    'thumbnail_dir',
    'weather_dir',
    'minify_dir',
    'myfiles_cache_dir',
    'video_dir',
    'submission_upload_tmp_dir'
);
