<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Steve
 * Date: 24/08/11
 * Time: 12:41 PM
 * To change this template use File | Settings | File Templates.
 */

/* Load Configuration Files */
require_once dirname(__FILE__) . '/../../www/conf/conf.php';

/* Load the Autoloader */
require_once dirname(__FILE__) . '/../../include/Autoloader.php';
$autoloader = Autoloader::initialise();

require_once dirname(__FILE__) . '/../../www/include/stdfuncs.php';

require_once 'PHPUnit/Framework/TestCase.php';
