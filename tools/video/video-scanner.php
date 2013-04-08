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

use \Schoolbox\Video;

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');


$mapper = new \Schoolbox\Video\QueueMapper();

$database = Database::getInstance();

$stmt= $database->prepare("SELECT file.id FROM file
					LEFT JOIN video_queue ON file.id = video_queue.file_id
					WHERE ( file.mime_type LIKE '%video%' OR file.mime_type LIKE '%flash%' OR file.mime_type LIKE '%realmedia%')
					AND video_queue.video_queue_id IS NULL"
);

$stmt->execute();

while($pendingFile = $stmt->fetch() ){

	print_r($pendingFile);

	$videoQ = new \Schoolbox\Video\Queue();

	$videoQ->setFileID($pendingFile['id']);
	$videoQ->setStatus( \Schoolbox\Video\QueueMapper::STATUS_PENDING );
	$videoQ->setType( \Schoolbox\Video\QueueMapper::FILE_TYPE_SBFS);

	$mapper->save($videoQ);

	unset($videoQ);
}

