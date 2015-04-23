<?php
/**
 * Video scanner and queue
 *
 * This script should be run regularily by cron, it picks up any videos uploaded into the filesystem and
 * places them in a queue to be processed via the video processor
 *
 * The filesystem itself is probably capable of doing this, but this will pickup any old videos aswell and process them
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use \Schoolbox\Video\QueueMapper;
use \Schoolbox\Video\Queue;

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$mapper = new QueueMapper();
$database = Database::getInstance();

/* Scans for videos in the SBFS */
$stmt= $database->prepare("
	SELECT file.hash FROM file
	LEFT JOIN video_queue ON file.id = video_queue.file_id AND video_queue.type = :type
	WHERE (file.mime_type LIKE 'video/%' || file.mime_type LIKE 'audio/%')
	AND video_queue.video_queue_id IS NULL
");

$stmt->execute(array(':type'=> QueueMapper::FILE_TYPE_SBFS));

while ($pendingFile = $stmt->fetch()) {
	$videoQ = new Queue();
	$videoQ->setHash($pendingFile['hash']);
	$mapper->save($videoQ);

	unset($videoQ);
}

/* Scans for videos in student submissions */
$stmt= $database->prepare("SELECT student_submission.hash FROM student_submission
					LEFT JOIN video_queue ON student_submission.id = video_queue.file_id AND video_queue.type = :type
					WHERE ( student_submission.mime_type LIKE 'video/%')
					AND video_queue.video_queue_id IS NULL"
);

$stmt->execute(array(':type'=> QueueMapper::FILE_TYPE_SUBMISSION));

while($pendingFile = $stmt->fetch() ){
	$videoQ = new Queue();
	$videoQ->setHash($pendingFile['hash']);
	$videoQ->setStatus( QueueMapper::STATUS_PENDING );
	$mapper->save($videoQ);

	unset($videoQ);
}

/* Videos uploaded into MyFiles
 */
$stmt= $database->prepare("
	SELECT myFile.hash FROM myFile
	LEFT JOIN video_queue ON myFile.id = video_queue.file_id AND video_queue.type = :type
	WHERE (myFile.mime_type LIKE 'video/%')
	AND video_queue.video_queue_id IS NULL
");

$stmt->execute(array(':type'=> QueueMapper::FILE_TYPE_MYFILE));

while ($pendingFile = $stmt->fetch()) {
	$videoQ = new Queue();
	$videoQ->setHash($pendingFile['hash']);
	$videoQ->setStatus(QueueMapper::STATUS_PENDING);
	$mapper->save($videoQ);

	unset($videoQ);
}

