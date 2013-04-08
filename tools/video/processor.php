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
namespace Schoolbox\Video;


use Schoolbox\Video\QueueMapper;

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');


$mapper = new QueueMapper();

$pendingQueue = $mapper->getPending();

foreach($pendingQueue as $vid) {

	/* Update video to indicate processing has begun */
	$vid->setStatus(QueueMapper::STATUS_PROCESSING);
	$mapper->save($vid);

	/* Process video */
	if($vid->processVideo() == true) {
		$vid->setStatus(QueueMapper::STATUS_COMPLETED);
	}
	else {
		$vid->setStatus(QueueMapper::STATUS_FAILED);
	}

	$mapper->save($vid);

}
