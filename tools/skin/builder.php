<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');


define('SKIN_FILE', dirname(__FILE__) .'/../../www/css/foundation/utils/_skin.scss');

$opts  = getopt('f', ['force']);
$force = isset($opts['f']) || isset($opts['force']);

// Skin builder is off? don't set any vars
$vars = [];
if (Config::get('skin_builder')) {
    $vars = [
        'primary-color'     => Config::get('skin_primary_colour'),
        'secondary-color'   => Config::get('skin_secondary_colour'),
        'tertiary-color'    => Config::get('skin_tertiary_colour'),
        'body-bg'           => Config::get('skin_body_bg_colour'),
        'tabbar-bg'         => Config::get('skin_tab_bg_colour'),
        'off-canvas-bg'     => Config::get('skin_off_canvas_bg_colour'),
    ];
}

$origSha = sha1_file(SKIN_FILE);
$newSha  = null;
$fp      = fopen(SKIN_FILE, 'w');
if($fp) {
    fputs($fp, "// Do not edit, automatically created by tools/skin/builder.php\n");

    foreach ($vars as $var => $val) {
        fputs($fp, '$'.$var.': '.$val.";\n");
    }
    fclose($fp);

    $newSha = sha1_file(SKIN_FILE);
}

if ($origSha != $newSha || $force) {
    // Compile the new sass file.
    $infile  = SITE_root . '/www/css/foundation/chrome-foundation.scss';
    $outfile = SITE_root . '/www/css/foundation/chrome.css';
    $tmpfile = tempnam(sys_get_temp_dir(), 'css');
    $cmd     = sprintf('sass %s > %s', escapeshellarg($infile), escapeshellarg($tmpfile));
    exec($cmd, $output, $outputCode);
    if ($outputCode === 0) {
        copy($tmpfile, $outfile);
    } else {
        // something went wrong
    }
    exit($outputCode);
}
exit(0);
