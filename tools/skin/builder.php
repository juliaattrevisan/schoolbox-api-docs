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
define('CUSTOM_CSS_FILE', dirname(__FILE__) .'/../../www/css/foundation/utils/_custom.scss');


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
        'body-bg'         => Config::get('skin_colour_body_bg'),
        'button-input-scale' => Config::get('skin_button_input_scale'),
        'login-form-background-color' => Config::get('skin_colour_login_background'),
    ];
} else {
    $logger->info('Skin builder is off - building using default color settings');
}

if(is_readable(SKIN_FILE) && is_readable(CUSTOM_CSS_FILE)) {
    $origSha = sha1_file(SKIN_FILE);
    $origCustomSha = sha1_file(CUSTOM_CSS_FILE);
    $logger->info("Skin has been built before (hash: $origSha)");
}
else {
    // File doesnt exist, force the build
    $logger->info('Skin has not been built before - forcing build');
    $force = true;
    $origSha = null;
}

$newSha  = null;
$customSha = null;
$fp      = fopen(SKIN_FILE, 'w');
if($fp) {
    $logger->info('Creating skin file...');
    fputs($fp, "// Do not edit, automatically created by tools/skin/builder.php\n");

    foreach ($vars as $var => $val) {
        fputs($fp, '$'.$var.': '.$val.";\n");
    }

    fclose($fp);

    /* Output custom css */
    if($cfp = fopen(CUSTOM_CSS_FILE, 'w')) {
        fputs($cfp, "// Do not edit, automatically created by tools/skin/builder.php\n");
        fputs($cfp, Cfg::get('skin_custom_css'));
        fclose($cfp);
    }

    $newSha = sha1_file(SKIN_FILE);
    $newCustomSha = sha1_file(CUSTOM_CSS_FILE);
    $logger->info("Skin file created (hash: $newSha)");
}

$siteLocation = Config::get('site_location');
if ($origSha != $newSha || $origCustomSha != $newCustomSha || $force) {
    $logger->info('Skin has changed, or build was forced - rebuilding');
    // Compile the new sass file.
    $infile  = SITE_root . '/www/css/foundation/chrome-foundation.scss';
    $outfile = SITE_root . '/www/css/foundation/'.$siteLocation.'/chrome.css';
    $outdir  = dirname($outfile);
    $ie9file = SITE_root . '/www/css/foundation/'.$siteLocation.'/chrome-ie9.css';
    $tmpfile = tempnam(sys_get_temp_dir(), 'css');
    $cmd     = sprintf('sass %s %s', escapeshellarg($infile), escapeshellarg($tmpfile));
    $logger->info("Building SASS...");
    exec($cmd, $output, $outputCode);
    if ($outputCode === 0) {
        $logger->info("SASS built - copying to CSS location");
        if (!is_dir($outdir)) {
            mkdir($outdir);
        }
        copy($tmpfile, $outfile);
        // Copy the sourcemap
        if (is_readable($tmpfile.'.map')) {
            copy($tmpfile.'.map', $outfile.'.map');
        }

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
            // Something went wrong through blessc, that's a warning for now rather than an error
            $logger->warning("Something went wrong during blessc (exit code: $outputCode)");
            $logger->info("Command: $cmd");
            $logger->info("Output:");
            foreach ($output as $line) {
                $logger->info($line);
            }
            $outputCode = 0;
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
