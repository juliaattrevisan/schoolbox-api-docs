<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* When components are added, they go through POST
 */
if (isset($_POST['uid'])) {
	$uid = Scrub::text($_POST['uid']);
}
else {
	$uid = $this->arg[0];
}

$imageScroller = new Eportfolio_Image_Scroller($this, $uid, $config);
$imageScroller->setComponentHandle($componentHandle);
$imageScroller->render();
