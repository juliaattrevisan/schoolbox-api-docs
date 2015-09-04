<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');


define('SKIN_FILE', dirname(__FILE__) .'/../../www/css/foundation/utils/_skin.scss');


$vars = array(  'primary-color'     => Config::get('skin_primary_colour'),
                'secondary-color'   => Config::get('skin_secondary_colour'),
                'tertiary-color'    => Config::get('skin_tertiary_colour'),
                'body-bg'           => Config::get('skin_body_bg_colour'),
                'tabbar-bg'         => Config::get('skin_tab_bg_colour'),
                'off-canvas-bg'     => Config::get('skin_off_canvas_bg_colour'),
);

$origSha = sha1_file(SKIN_FILE);

$fp = fopen(SKIN_FILE, 'w');

if($fp) {
    fputs($fp, "// Do not edit, automatically created by tools/skin/builder.php\n");

    foreach ($vars as $var => $val) {
        fputs($fp, '$'.$var.': '.$val.";\n");
    }
    fclose($fp);

    $newSha = sha1_file(SKIN_FILE);
}

if($origSha != $newSha) {
    exec('cd ../../; grunt sass');
}


