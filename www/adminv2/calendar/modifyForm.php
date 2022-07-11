<?php

use Sabre\VObject\InvalidDataException;
use Sabre\VObject\ParseException;
use Schoolbox\Calendar\Cache\Vcal;
use Schoolbox\Calendar\Event;
use Schoolbox\Collection;

/** @var SitePage $this */
if (!$this->getDi()->get('session')->get('user')->isSuperuser()) {
    $this->error(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW, '', 403);
}

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
                $config->setLuEventType((int)$attribs['luEventType']);
            }

            /* Process Duration for External DB connectors
             */
            if ($config instanceof Calendar_Api_Config_Connector_ExternalConstrained) {
                $config->setDuration($attribs['duration']);
            } elseif ($config instanceof Calendar_Api_Config_Connector_Vcal) {
                /* Add urls
                 */
                //trim the url as the front end won't always catch the whitespace in the url
                $config->setUrl(trim($attribs['url']));
                $config->setAuthorization($attribs['authorization']);


                /* Add permissions for campus (or use the default empty array if not chosen)
                 */
                if (is_array($attribs['campus'])) {
                    $config->setCampus($attribs['campus']);
                } else {
                    $config->setCampus([]);
                }

                /* Add permission for groups (or use the default empty array if not chosen)
                 */
                if (is_array($attribs['groupCheck'])) {
                    $config->setGroups($attribs['groupCheck']);
                } else {
                    $config->setGroups([]);
                }
            }

            /* Update and save config
             */
            $config->setLabel(trim($attribs['label']));
            $configRecord->updateConfig($config);
            Calendar_Api_Config_Mapper::getInstance()->save([$configRecord]);
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
            $allConfigs = Calendar_Api_Config_Mapper::getInstance()->findByColumnList([
                'event_type' => $bits[0],
                'connector_class' => $bits[1],
            ]);

            /* Add it
             */
            if (!$allConfigs->count()) {
                $className = 'Calendar_Api_Config_Connector_' . Scrub::text($bits[1]);
                if (class_exists($className)) {
                    /** @var Calendar_Api_Config_Connector_Abstract $config */
                    $config = new $className();
                    $config->setEventType($bits[0]);
                    $config->setConnectorClass($bits[1]);
                    $config->setLabel($config->getDefaultLabel());
                    $configRecord = new Calendar_Api_Config_Record();
                    $configRecord->setEventType($bits[0]);
                    $configRecord->setConnectorClass($bits[1]);
                    $configRecord->updateConfig($config);

                    /* This is active
                     */
                    $configRecord->activate();

                    /* Ram in this new record
                     */
                    $allConfigs = new Collection([$configRecord]);
                }
            }

            /* Update it
             * Change status for each config
             */
            foreach ($allConfigs as $currentConfig) {
                $currentConfig->activate();
                //reset failed count, as they user may have fixed the issue.... may..
                $currentConfig->setFailedCount(0);
                Calendar_Api_Config_Mapper::getInstance()->save([$currentConfig]);
            }
        }

        // Cache downloaded and parsed vCal for all vCal configs
        /** @var SitePage $this */
        $cache = new Vcal($this->getDi()->get('redis.cache'));

        /** @var iterable&Calendar_Api_Config_Record[] $configs */
        $enabledConfigs = Calendar_Api_Config_Mapper::getInstance()->getAllConfigRecords(true);
        foreach ($enabledConfigs as $enabledConfig) {
            $decodedConfig = $enabledConfig->decodeConfig();
            if ($decodedConfig instanceof Calendar_Api_Config_Connector_Vcal && $decodedConfig->getUrl() && !$enabledConfig->isDead()) {
                try {
                    $cache->write($decodedConfig);
                } catch (InvalidArgumentException | ParseException | InvalidDataException $e) {
                    // There was a failure when writing the cache,
                    // or reading from the URL in the config.
                    // Whatever the case, continue
                } catch (Schoolbox\Application\Exception\Exception $e) {
                    $enabledConfig->addFailedCount();
                    Calendar_Api_Config_Mapper::getInstance()->save($enabledConfig);
                }
            }
        }
    }
} elseif (isset($_POST['action']) && $_POST['action'] == 'cleanup') {
    /* Mass Cleanup
     */
    if (isset($_POST['cleanupFrom']) && isset($_POST['cleanupTo'])) {
        /* Change selected date range for mass cleanup
         * noop
         */
    } elseif (is_array($_POST['cleanupEventId']) && count($_POST['cleanupEventId'])) {
        /* Mass cleanup of selected events
         */
        SitePage::extendExecutionTime();

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
                /** @var Event $event */
                $event = $eventMapper->findById($currentEventId);

                /* Purge existing sids
                 */
                $userRelationMapper->deleteByColumn('event_id', $event->getId());
                $categoryRelationMapper->deleteByColumn('event_id', $event->getId());
                $roleRelationMapper->deleteByColumn('event_id', $event->getId());
                $campusRelationMapper->deleteByColumn('event_id', $event->getId());

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
