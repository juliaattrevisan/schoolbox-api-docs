<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/* Configuration settings
 */
$salt = 'MSAHashKey';
$url = 'https://msa.tps.vic.edu.au';

/* Compute redirect destination
 */
$hash = "{$salt}{$_SESSION['user']->getUsername()}";
$key = md5($hash);
$username = urlencode($_SESSION['user']->getUsername());

$destination = "{$url}/?id={$username}&key={$key}";
header('Location:' . $destination, false, 301);
