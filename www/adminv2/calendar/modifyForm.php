<?php

/* Update the options for each connector
 * Currently, only Vcal has options which can be modified
 */
if (is_array($_POST['record']) && count($_POST['record'])) {
	foreach($_POST['record'] as $id => $attribs) {
		$configRecord = Calendar_Api_Config_Mapper::getInstance()->findById($id);

		$config = $configRecord->decodeConfig();

		/* Process multiple Vcals
		 */
		if ($attribs['connectorClass'] == 'Vcal') {

			/* Add eventType option
			 */
			$config->setLuEventType($attribs['luEventType']);

			/* Add urls
			 */
			$config->setUrl($attribs['url']);

			/* Add permissions for campus (or use the default empty array if not chosen)
			 */
			if (is_array($attribs['campus'])) {
				$config->setCampus($attribs['campus']);
			}
			else {
				$config->setCampus(array());
			}

			/* Add permission for groups (or use the default empty array if not chosen)
			 */
			if (is_array($attribs['groupCheck'])) {
				$config->setGroups($attribs['groupCheck']);
			}
			else {
				$config->setGroups(array());
			}
		}

		/* Update and save config
		 */
		$configRecord->updateConfig($config);

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

		/* Ensure that each connector has at least one config
		 * If it doesn't have one, add one
		 */
		$allConfigs = Calendar_Api_Config_Mapper::getInstance()->findByColumnList(array(
			'event_type' => $bits[0],
			'connector_class' => $bits[1],
		));

		/* Add it
		 */
		if (!$allConfigs->count()) {
			$className = 'Calendar_Api_Config_Connector_' . Scrub::text($bits[1]);

			if (class_exists($className)) {
				$config = new $className();
				$config->setEventType(Scrub::text($bits[0]));
				$config->setConnectorClass(Scrub::text($bits[1]));

				$configRecord = new Calendar_Api_Config_Record();
				$configRecord->setEventType(Scrub::text($bits[0]));
				$configRecord->setConnectorClass(Scrub::text($bits[1]));
				$configRecord->updateConfig($config);

				/* This is active
				 */
				$configRecord->activate();

				/* Ram in this new record
				 */
				$allConfigs->set(array($configRecord));
			}
		}

		/* Update it
		 * Change status for each config
		 */
		foreach($allConfigs as $currentConfig) {
			$currentConfig->activate();

			Calendar_Api_Config_Mapper::getInstance()->save(array($currentConfig));
		}
	}
}

$this->backToTheFuture(1);