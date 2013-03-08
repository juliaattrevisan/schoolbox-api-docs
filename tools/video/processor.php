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


$videoStore = '/vagrant/data/video';
$sourceStore = Cfg::get('file_dir');
$sourceFile = $sourceStore.'/'.$argv[1].'.dat';

$processString = 'avconv -i '.$sourceFile.' -c:v libx264 -preset slow -b 512k '.$arv[1].'.mp4';
$thumbnailString = 'avconv -i '.$sourceFile.' -f image2 -ss 10 -s 64x64 -frames:v 1 '.$arv[1].'-64.jpg';

exec($processString);
exec($thumbnailString);

