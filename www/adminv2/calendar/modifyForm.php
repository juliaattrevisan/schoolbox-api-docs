<?php

/* Update the options for each connector
 */
if (is_array($_POST['record']) && count($_POST['record'])) {
    foreach ($_POST['record'] as $id => $attribs) {
        /** @var Calendar_Api_Config_Record $configRecord */
        $configRecord = Calendar_Api_Config_Mapper::getInstance()->findById($id);
        $config = $configRecord->decodeConfig();
     /* Process multiple Vcals
		 */
        if ($config instanceof Calendar_Api_Config_Connector_Vcal) {
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
            } else {
                $config->setCampus(array());
            }
      /* Add permission for groups (or use the default empty array if not chosen)
			 */
            if (is_array($attribs['groupCheck'])) {
                $config->setGroups($attribs['groupCheck']);
            } else {
                $config->setGroups(array());
            }
        } elseif ($config instanceof Calendar_Api_Config_Connector_External) {
      // External connector: only set event type
            $config->setLuEventType($attribs['luEventType']);
        }
     /* Update and save config
		 */
        $config->setLabel($attribs['label']);
        $configRecord->updateConfig($config);
        Calendar_Api_Config_Mapper::getInstance()->save(array($configRecord));
    }
} elseif (isset($_POST['cleanupFrom']) && isset($_POST['cleanupTo'])) {
}

/* Update the status of each connector by deactivating everything,
 * then selectively reactivating
 */
Calendar_Api_Config_Mapper::getInstance()->deactivateAll();

if (is_array($_POST['active']) && count($_POST['active'])) {
    foreach ($_POST['active'] as $currentActive) {
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
                /** @var Calendar_Api_Config_Connector_Abstract $config */
                $config = new $className();
                $config->setEventType(Scrub::text($bits[0]));
                $config->setConnectorClass(Scrub::text($bits[1]));
                $config->setLabel($config->getDefaultLabel());
                $configRecord = new Calendar_Api_Config_Record();
                $configRecord->setEventType(Scrub::text($bits[0]));
                $configRecord->setConnectorClass(Scrub::text($bits[1]));
                $configRecord->updateConfig($config);
       /* This is active
				 */
                $configRecord->activate();
       /* Ram in this new record
				 */
                $allConfigs = new Schoolbox\Collection(array($configRecord));
            }
        }
     /* Update it
		 * Change status for each config
		 */
        foreach ($allConfigs as $currentConfig) {
            $currentConfig->activate();
            Calendar_Api_Config_Mapper::getInstance()->save(array($currentConfig));
        }
    }
} elseif (is_array($_POST['cleanupEventId']) && count($_POST['cleanupEventId'])) {
    ini_set('max_execution_time', 300);

    /* Remove all the events
	 */
    $eventMapper = new \Schoolbox\Calendar\EventMapper();
    $userRelationMapper = new \Schoolbox\Calendar\Relation\PersonalMapper();
    $categoryRelationMapper = new \Schoolbox\Calendar\Relation\CategoryMapper();
    $roleRelationMapper = new \Schoolbox\Calendar\Relation\RoleMapper();
    $campusRelationMapper = new \Schoolbox\Calendar\Relation\CampusMapper();

    /* Purge all the relations as well
	 */
    foreach ($_POST['cleanupEventId'] as $currentEventId) {
        try {
            $event = $eventMapper->findById($currentEventId);
      /* Purge existing sids
			 */
            $userRelationMapper->cleanse($event);
            $categoryRelationMapper->cleanse($event);
            $roleRelationMapper->cleanse($event);
            $campusRelationMapper->cleanse($event);
      /* Purge event
			 */
            $eventMapper->delete($event);
        } catch (\Exception $e) {
        }
    }
}

$this->backToTheFuture(1);
