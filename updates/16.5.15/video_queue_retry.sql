-- Reattempt all videos that incorrectly stated 'Timed Out' as the failure cause
UPDATE `video_queue` SET status='pending' WHERE status='failed' AND message LIKE 'Timed out%';
