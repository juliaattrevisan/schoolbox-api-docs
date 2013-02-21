<?php

switch($_POST['connectorClass']) {
	case 'Vcal':
		if (is_array($_POST['url']) && count($_POST['url'])) {
			foreach($_POST['url'] as $key => $value) {

				$config = new Calendar_Api_Config_Type_Vcal();
				$config->setId($key);
				$config->setUrl($value);

				$configRecord = Calendar_Api_Config_Mapper::getInstance()->findById($key);
				$configRecord->updateConfig($config);

				Calendar_Api_Config_Mapper::getInstance()->save(array($configRecord));
			}
		}

	break;

	default:
	break;
}

//new Alaress_Probe($_POST);
exit();