<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../../library/Bootstrap.php';

date_default_timezone_set('Australia/Melbourne');

$limits = array(
    0 => 500, // nil verbosity: exceptional behaviour only
    1 => 250, // min verbosity: significant events
    2 => 200, // mod verbosity: most things
    3 => 100  // max verbosity: EV-ER-Y-THING...
);

$fields = array('pid','mem','truemem', 'level');

$opts = getopt('v', array_merge($fields, array('porcelain')));

$display = array();

foreach($fields as $f) {
    if(isset($opts[$f])) {
        $display[] = $f;
    }
}

$threshold = 0;
if(isset($opts['v'])) {
    $threshold = 1;
    if(is_array($opts['v'])) {
        $threshold = min(3, count($opts['v']));
    }
}

$l = new \Alaress\Lumberjack\Logger(STDOUT, STDERR, $threshold, $display, isset($opts['porcelain']));

$l->info("CLI Logger Demo", array('char' => '>'));

$l->info("Test different levels with -[v|vv|vvv] (currently $threshold/3)");
$l->info("Test different meta with [--pid|--mem|--truemem|--level]");
$l->info("Test human vs. machine parseable with --porcelain");

// covered by level 3
$l->debug("This is DEBUG(100), which should include info that might be useful for bugfinding");

// covered by level 2
$l->info("This is INFO(200), nothing special, just something that might be helpful; secondary verbosity...");
$l->info("...which can accept a prefix character", array('char' => '-'));

// covered by level 1
$l->notice("This is NOTICE(250), your baseline verbosity level");
$l->notice("A NOTICE(250) will also accept any single character in \$context['char'] for its prefix...", array('char' => '~'));
$l->notice("...some of which have custom colourings", array('char' => '+'));
$l->warning("A WARNING(300) should be raised if we're getting into a state that's probably going to be bad");
$l->error("An ERROR(400) is when we get into a known but manageable error state");

// covered by level 0
$l->critical("Something CRITICAL(500) is when an exception or error goes uncaught");
$l->alert("An ALERT(550) means an external service other than MySQL is offline");
$l->emergency("If you get an EMERGENCY(600), MySQL is offline", array(new PDOException("MySQL went away")));
$l->debug("[ ] => You can also force a completely custom log at any level if it's formatted like this one (don't forget the trailing newline)\n");

