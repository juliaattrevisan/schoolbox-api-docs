<?php

/* Remove record if it existed
 */
if (is_numeric($_POST['id'])) {
	$configRecord = Calendar_Api_Config_Mapper::getInstance()->findById(Scrub::text($_POST['id']));

	Calendar_Api_Config_Mapper::getInstance()->delete(array($configRecord));
}

exit();