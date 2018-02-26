<?php
/**
 * Display PTO website in an iframe with SSO
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\PTO\Loader as PTOLoader;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Error\Mapper as ErrorMapper;

$ptoLoader = new PTOLoader(new ConfigLoader(), $_SESSION["user"]);

try {
    header('Location: '. $ptoLoader->generateRemoteUrl());
    exit;
}
catch (DomainException $e) {
    error(ErrorMapper::ERROR_INFORMATION_MISSING);
}