<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Steve
 * Date: 24/08/11
 * Time: 12:41 PM
 * To change this template use File | Settings | File Templates.
 */

require_once __DIR__ . '/../../www/conf/conf.php';
require_once __DIR__ . '/../../www/include/stdfuncs.php';
require_once __DIR__ . '/../../library/Autoloader.php';
Autoloader::initialise();

// sorry.
Schoolbox\Mapper::di(new Aura\Di\Container(new Aura\Di\Factory()));