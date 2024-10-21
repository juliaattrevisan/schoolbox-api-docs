<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

if (isset($this)) {
    /**
     * No sitepages allowed!
     * @var SitePage $this
     */
    $this->error(19);
}
require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->setupSentryTrace(__FILE__);


if (empty($_POST['key'])) {
    http_response_code(400);
    die("How did you even get here?");
} else {
    $key = $_POST['key'];
}

$data =& $_SESSION["pantry-$key"];

if (empty($data['key'])) {
    $data = [ 'key' => $key ];
}

if (isset($_POST['part-1'])) {
    // execute the section 1 submit - just make them wait a bit
    usleep(1200000);
    die($key);
} elseif (isset($_POST['part-2'])) {
    // execute the section 2 submit

    // just slowing things down a little so you can see what's happening
    usleep(800000);

    // example of server-side validation
    if (empty($_POST['part-2-text'])) {
        http_response_code(400);
        die("Yes. I lied to you: the textbox is a required field. But this is an example of server-side validation for when you need things like DB checks. Enter some text and try again.");
    }

    // for show, uppercase, reverse and non-alpha strip ;)
    $data['part2'] = strip_tags($_POST['part-2-text']);
    $data['part2result'] = preg_replace('`[^A-Z]`', '', strrev(strtoupper($data['part2'])));

    // just send it straight back...
    die($data['part2result']);
} elseif (isset($_POST['part-3'])) {
    // execute the section 3 submit
    $data['hashUsed'] = true;
    die("Part 3 submitted");
} elseif (isset($_POST['part-4'])) {
    // execute the section 4 submit
    die("Part 4 submitted");
} else {
    // this is it: the final submit. ALL the formdata should be available here.
    echo "<div class='row'><div class='small-12 island'><h1>Progressive Form</h1><h2 class='subheader'>Final Submission Successful!</h2><p>Your data:</p>";

    echo "<section class='content'>";

    echo "<dl>";

    foreach ($data as $key => $value) {
        printf("<dt>%s</dt><dd>%s</dd>", $key, $value);
    }

    echo "</dl><a href='/pantry/form-progressive'>Go back to the form</a>";

    echo "</section></div></div>";

    exit;
}



// done!
exit;
