<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * @var SitePage $this
 */


$this->setFile(TEMPLATE_content, 'output.html');

if (count($this->arg) == 0) {
    $key = substr(sha1(time() + rand(0, PHP_INT_MAX)), 0, 6);
    $_SESSION["pantry-$key"] = [
        'key' => $key
    ];
} else {
    $key = substr($this->arg[0], 0, 6);

    if (empty($_SESSION["pantry-$key"])) {
        // invalid key
        $this->localRedir('/pantry/form-progressive');
    }
}

// cheeky shortcut...
$data =& $_SESSION["pantry-$key"];






$this->setVar('OUTPUT', $this->getTwig()->render('pantry/form-progressive.twig', $data));
