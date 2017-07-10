<?php
/**
 * Bootstrap for Codeception tests.
 *
 * (Identical to PHPUnit test bootstrap for now)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../www/conf/conf.php';
require_once __DIR__ . '/../../www/include/stdfuncs.php';
require_once __DIR__ . '/../../library/Autoloader.php';
Autoloader::initialise();

// sorry.
Schoolbox\Mapper::di(new Aura\Di\Container(new Aura\Di\Factory()));
