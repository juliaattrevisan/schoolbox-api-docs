<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$homepageId = 10;

$thread = new \SocialStream\Thread($homepageId);
$tree = $thread->getTree();

new Alaress_Probe($tree);

exit();