/* Delete from video queue where multiple of the same hash exists,
   as long as the remaining files are not failures
   */

DELETE v1 FROM video_queue v1
JOIN video_queue v2 ON v1.`hash` = v2.`hash` AND v1.`video_queue_id` < v2.`video_queue_id`
WHERE v2.status = 'completed';