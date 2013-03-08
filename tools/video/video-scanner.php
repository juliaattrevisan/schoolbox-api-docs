<?php
/**
 * Video scanner and queue
 *
 * This script should be run regularily by cron, it picks up any videos uploaded into the filesystem and
 * places them in a queue to be processed via the video processor
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');
