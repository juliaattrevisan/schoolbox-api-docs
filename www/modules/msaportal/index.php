<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* Configuration settings
 */
$salt = 'MSAHashKey';
$url = 'https://msa.tps.vic.edu.au';

/* Compute redirect destination
 */
$userId = $_SESSION['user']->getExternalId();
$hash = "{$salt}{$userId}";
$key = md5($hash);

$destination = "{$url}/?id={$userId}&key={$key}";

header('Location:'.$destination, false, 301);
