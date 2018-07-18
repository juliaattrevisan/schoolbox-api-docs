-- Apparently such broken states exist in production
UPDATE groups SET group_type = 'guest' WHERE group_type = '';
