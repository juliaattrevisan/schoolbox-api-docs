<?php

//
// Create new cache sub-directories
require_once __DIR__.'/../../include/Bootstrap.php';

$stream_dir = Cfg::get('stream_dir');
foreach (array('portrait', 'storage') as $subdir) {
	$dir = $stream_dir.'/'.$subdir;
	if (!file_exists($dir)) {
		mkdir($dir);
	}
}
