-- Removing pastoral flag count period from configs
DELETE FROM config WHERE name = 'pastoral_flag_count_period';
