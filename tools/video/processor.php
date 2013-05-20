<?php
/**
 * Video encoder and processor
 *
 * This script should be run regularily by cron, it picks up any videos in the queue
 * automatically converts them into playable videos
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use Schoolbox\Video\QueueMapper;

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');


$mapper = new QueueMapper();
$lock   = new Alaress_Lock(Cfg::get('video_dir').'/.lock');
if (!$lock->acquire()) {
	echo strftime("[%F %T] A video processor is already running.\n");
	exit;
}
echo strftime("[%F %T] Processing of videos started.\n");

$pendingQueue = $mapper->getPending();

$failed  = 0;
$success = 0;
foreach ($pendingQueue as $vid) {

	$file_id = $vid->getFileID();

	/* Update video to indicate processing has begun */
	echo strftime("[%F %T] Starting process of video #$file_id\n");
	$vid->setStatus(QueueMapper::STATUS_PROCESSING);
	$mapper->save($vid);

	/* Process video */
	try {
		if ($vid->processVideo() == true) {
			$vid->setStatus(QueueMapper::STATUS_COMPLETED);
		}
		else {
			$vid->setStatus(QueueMapper::STATUS_FAILED);
		}
	}
	catch (Exception $e) {
		$this->setMessage($e->getMessage());
		$vid->setStatus(QueueMapper::STATUS_FAILED);
	}

	if ($vid->getStatus() === QueueMapper::STATUS_FAILED) {
		fwrite(STDERR, strftime("[%F %T] ERROR: process of video #$file_id failed\n"));
		$failed++;
	}
	else {
		echo strftime("[%F %T] process of video #$file_id completed\n");
		$success++;
	}

	$mapper->save($vid);
}

$lock->release();
echo strftime("[%F %T] Processing of videos completed: ");
printf("%d succeeded, %d failed.\n", $success, $failed);

