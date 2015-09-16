UPDATE video_queue SET `status`='pending'
WHERE `status`='failed' AND message LIKE '%maybe incorrect parameters%' AND hash IS NOT NULL;