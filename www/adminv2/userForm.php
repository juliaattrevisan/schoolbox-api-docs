<?php

/**
 * This is now redundant - HTTP 401 Gone
 *
 *    |\      _,,,---,,_
 *   /,`.-'`'    -.  ;-;;,_
 *  |,4-  ) )-,_..;\ (  `'-'
 * '---''(_/--'  `-'\_)
 *
 */

require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');
SitePage::localRedir('/', null, 410);