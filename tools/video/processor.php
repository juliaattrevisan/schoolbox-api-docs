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

require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');


