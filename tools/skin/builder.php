<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

use Alaress\Lumberjack\Logger;

$logger = new Logger();

define('SKIN_FILE', dirname(__FILE__) .'/../../www/css/foundation/utils/_skin.scss');

$opts  = getopt('f', ['force']);
$force = isset($opts['f']) || isset($opts['force']);

// Skin builder is off? don't set any vars
$vars = [];
if (Config::get('skin_builder')) {
    $logger->info('Skin builder is on - building using color settings from skin builder');
    $vars = [
        'navigation-foreground' => Config::get('skin_colour_navigation_foreground'),
        'navigation-background' => Config::get('skin_colour_navigation_background'),
        'navigation-hover' => Config::get('skin_colour_navigation_hover'),
        'off-canvas-foreground' => Config::get('skin_colour_offcanvas_foreground'),
        'off-canvas-background' => Config::get('skin_colour_offcanvas_background'),
        'off-canvas-hover' => Config::get('skin_colour_offcanvas_hover'),
        'off-canvas' => Config::get('skin_colour_off_canvas'),
        'content-ui-foreground' => Config::get('skin_colour_content_ui_foreground'),
        'content-ui-background' => Config::get('skin_colour_content_ui_background'),
        'content-ui-hover' => Config::get('skin_colour_content_ui_hover'),
        'content-ui-selected' => Config::get('skin_colour_content_ui_selected'),
        'content-ui-submit-foreground' => Config::get('skin_colour_content_ui_submit_foreground'),
        'content-ui-submit-background' => Config::get('skin_colour_content_ui_submit_background'),
        'accent-foreground' => Config::get('skin_colour_accent_foreground'),
        'accent-background' => Config::get('skin_colour_accent_background'),
        'accent-hover' => Config::get('skin_colour_accent_hover'),
        'logo-background' => Config::get('skin_colour_logo_background'),
        'body-bg'         => Config::get('skin_body_bg_colour'),

//        'primary-color'   => Config::get('skin_primary_colour'),
//        'secondary-color' => Config::get('skin_secondary_colour'),
//        'tertiary-color'  => Config::get('skin_tertiary_colour'),
    ];
} else {
    $logger->info('Skin builder is off - building using default color settings');
}

if(is_readable(SKIN_FILE)) {
    $origSha = sha1_file(SKIN_FILE);
    $logger->info("Skin has been built before (hash: $origSha)");
}
else {
    // File doesnt exist, force the build
    $logger->info('Skin has not been built before - forcing build');
    $force = true;
    $origSha = null;
}
$newSha  = null;
$fp      = fopen(SKIN_FILE, 'w');
if($fp) {
    $logger->info('Creating skin file...');
    fputs($fp, "// Do not edit, automatically created by tools/skin/builder.php\n");

    foreach ($vars as $var => $val) {
        fputs($fp, '$'.$var.': '.$val.";\n");
    }
    fclose($fp);

    $newSha = sha1_file(SKIN_FILE);
    $logger->info("Skin file created (hash: $newSha)");
}

if ($origSha != $newSha || $force) {
    $logger->info('Skin has changed, or build was forced - rebuilding');
    // Compile the new sass file.
    $infile  = SITE_root . '/www/css/foundation/chrome-foundation.scss';
    $outfile = SITE_root . '/www/css/foundation/chrome.css';
    $ie9file = SITE_root . '/www/css/foundation/chrome-ie9.css';
    $tmpfile = tempnam(sys_get_temp_dir(), 'css');
    $cmd     = sprintf('sass %s > %s', escapeshellarg($infile), escapeshellarg($tmpfile));
    $logger->info("Building SASS...");
    exec($cmd, $output, $outputCode);
    if ($outputCode === 0) {
        $logger->info("SASS built - copying to CSS location");
        copy($tmpfile, $outfile);

        $logger->info("Splitting for IE < 10 using blessc...");
        $blessCmd = 'blessc';
        exec('which '.escapeshellarg($blessCmd), $output, $outputCode);
        if ($outputCode !== 0) {
            $logger->info("blessc doesn't exist - assuming in node_modules");
            $blessCmd = SITE_root.'/node_modules/.bin/blessc';
        }
        $blessCmd = sprintf('%s %s %s', escapeshellarg($blessCmd), escapeshellarg($outfile), escapeshellarg($ie9file));
        exec($blessCmd, $output, $outputCode);
        if ($outputCode === 0) {
            $logger->info("Splitting for IE < 10 complete");
            $logger->info("Skin built successfully");
        } else {
            // Something went wrong through blessc
            $logger->error("Something went wrong during blessc (exit code: $outputCode)");
            $logger->info("Command: $cmd");
            $logger->info("Output:");
            foreach ($output as $line) {
                $logger->info($line);
            }
        }
    } else {
        // something went wrong
        $logger->error("Something went wrong during SASS build process! (exit code: $outputCode)");
        $logger->info("Command: $cmd");
        $logger->info("Output:");
        foreach ($output as $line) {
            $logger->info($line);
        }
    }
    exit($outputCode);
} else {
    $logger->info('Skin has not changed, and build was not forced');
}
exit(0);
