<?php

/* Update the options for each connector
 * Currently, only Vcal has options which can be modified
 */
switch($_POST['connectorClass']) {
	case 'Vcal':
		if (is_array($_POST['url']) && count($_POST['url'])) {
			foreach($_POST['url'] as $key => $value) {

				/* Update each url
				 */
				$config = new Calendar_Api_Config_Type_Vcal();
				$config->setId($key);
				$config->setUrl($value);

				$configRecord = Calendar_Api_Config_Mapper::getInstance()->findById($key);
				$configRecord->updateConfig($config);

				Calendar_Api_Config_Mapper::getInstance()->save(array($configRecord));
			}

			$this->backToTheFuture(1);
		}

		break;

	default:
	break;
}

exit();