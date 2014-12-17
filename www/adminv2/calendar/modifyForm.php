<?php

/* Use $_POST['action'] to separate screens
 */
if (isset($_POST['action']) && $_POST['action'] == 'subscribe') {
/* Subscription settings
     */
    if (is_array($_POST['record']) && count($_POST['record'])) {
/* Update the options for each connector
         */
        foreach ($_POST['record'] as $id => $attribs) {
            /** @var Calendar_Api_Config_Record $configRecord */
            $configRecord = Calendar_Api_Config_Mapper::getInstance()->findById($id);
            $config = $configRecord->decodeConfig();
            /* Process multiple Vcals
             */
            if (isset($attribs['luEventType'])) {
                /* Add eventType option
                 * For now, assume any config can have an event type.
                 * It's ignored for configs for which this is not set anyway.
                 */
                $config->setLuEventType($attribs['luEventType']);
            }
            if ($config instanceof Calendar_Api_Config_Connector_Vcal) {
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
            }
            /* Update and save config
             */
            $config->setLabel($attribs['label']);
            $configRecord->updateConfig($config);
            Calendar_Api_Config_Mapper::getInstance()->save(array($configRecord));
        }
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
            $allConfigs = Calendar_Api_Config_Mapper::getInstance()->findByColumnList(
                array(
                    'event_type' => $bits[0],
                    'connector_class' => $bits[1],
                )
            );
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
    }
} elseif (isset($_POST['action']) && $_POST['action'] == 'cleanup') {
/* Mass Cleanup
     */
    if (isset($_POST['cleanupFrom']) && isset($_POST['cleanupTo'])) {
        /* Change selected date range for mass cleanup
         */
        // noop
    } elseif (is_array($_POST['cleanupEventId']) && count($_POST['cleanupEventId'])) {
        /* Mass cleanup of selected events
         */
        SitePage::extendExecutionTime(300);

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
                // noop
            }
        }
    }
}

$this->backToTheFuture(1);
