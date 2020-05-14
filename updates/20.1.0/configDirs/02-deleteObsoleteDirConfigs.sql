-- These configs are no longer needed, so they can be removed
DELETE FROM config WHERE name IN (
    'template_dir',
    'file_dir',
    'myfile_dir',
    'submission_dir',
    'returns_dir',
    'dropbox_dir',
    'thumbnail_dir',
    'xml_dir,',
    'weather_dir',
    'minify_dir',
    'myfiles_cache_dir',
    'video_dir',
    'submission_upload_tmp_dir'
);
