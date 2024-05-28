UPDATE submission_box
    SET enable_files = 1,
    enable_offline = 1,
    enable_text = 1,
    enable_policy = 1
WHERE enable_files = 0;
