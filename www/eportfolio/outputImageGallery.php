<?php

/* When components are added, they go through POST
 */
if (isset($_POST['uid'])) {
	$uid = Scrub::text($_POST['uid']);
}
else {
	$uid = $this->arg[0];
}

$imageGallery = new Eportfolio_Image_Gallery($this, $uid, $config, $componentHandle);
$imageGallery->render();

