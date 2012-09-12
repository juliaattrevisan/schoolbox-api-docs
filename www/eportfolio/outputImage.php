<?php

/* When components are added, they go through POST
 */
if (isset($_POST['uid'])) {
	$uid = Scrub::text($_POST['uid']);
}
else {
	$uid = $this->arg[0];
}

$featuredImage = new Eportfolio_Image_Feature($this, $uid, $config, $componentHandle, $eportfolioHandler);
$featuredImage->render();
