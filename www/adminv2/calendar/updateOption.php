<?php

new Alaress_Probe($_POST);

/* Update the options for each connector
 * Currently, only Vcal has options which can be modified
 */
if (is_array($_POST['record']) && count($_POST['record'])) {
	foreach($_POST['record'] as $id => $attribs) {
		$configRecord = Calendar_Api_Config_Mapper::getInstance()->findById($id);

		/* Process multiple Vcals
		 */
		if ($attribs['connectorClass'] == 'Vcal') {

			/* Update each url
			 */
			$config = $configRecord->decodeConfig();
			$config->setUrl($attribs['url']);

			$configRecord->updateConfig($config);
		}

		Calendar_Api_Config_Mapper::getInstance()->save(array($configRecord));
	}
}

/* Update the status of each connector by deactivating everything,
 * then selectively reactivating
 */
Calendar_Api_Config_Mapper::getInstance()->deactivateAll();

if (is_array($_POST['active']) && count($_POST['active'])) {
	foreach($_POST['active'] as $currentActive) {
		$bits = explode('_', $currentActive);

		Calendar_Api_Config_Mapper::getInstance()->activate($bits[0], $bits[1]);
	}
}

$this->backToTheFuture(1);