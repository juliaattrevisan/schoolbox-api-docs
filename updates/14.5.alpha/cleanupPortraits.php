<?php

/* Script designed to clean up portrait photos following the issue with escapeshellcmd */

require_once __DIR__.'/../../include/Bootstrap.php';

$affected = array('synergetic', 'schoolpro', 'maze');

if(in_array(Config::get('external_type'),$affected )) {

    /* Remove original portraits */
    $pattern = Config::get('portrait_dir') . '/*.jpg';

    foreach (glob($pattern) as $oldCacheFile) {
        unlink($oldCacheFile);
    }

    /* Remove cached versions */
    $pattern = Config::get('stream_dir') . '/portrait/*.dat';
    foreach (glob($pattern) as $oldCacheFile) {
        unlink($oldCacheFile);
    }
}