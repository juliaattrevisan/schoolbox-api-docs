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
			$config->setUrl($value);

			$configRecord->updateConfig($config);
		}

//		Calendar_Api_Config_Mapper::getInstance()->save(array($configRecord));
	}
}

/* Update the status of each connector
 */
$allConfigRecords = Calendar_Api_Config_Mapper::getInstance()->findAll();

