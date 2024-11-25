<?php

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as AlaressDatabaseException;
use Calendar\Api\ModuleChecker;
use Schoolbox\Calendar\Component\Dashboard\ApiFactory;
use Schoolbox\Calendar\Component\Dashboard\FilterCodec;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Permission\Access\UserAccess;

/**
 * These are the checkbox selections that users can tick
 * to filter the calendar payload
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_CalendarFilter extends Component_Config_Abstract
{
    /**
     * Serialize multidimensional data so it can be persisted to the db
     *
     * @return false|mixed|string
     * @throws JsonException
     */
    public function dehydrate($value, Component_ControllerAbstract $controller)
    {
        return (new FilterCodec())->encode($value);
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $di = $page->getDi();

        try {
            /** @var IConnector $databaseExternal */
            $databaseExternal = $di->get('databaseExternal');
        } catch (AlaressDatabaseException $e) {
            $databaseExternal = null;
        }

        /** @var AssessmentDataSource $assessmentDataSource */
        $assessmentDataSource  = $di->get('assessmentDataSource');
        /** @var IConfigLoader $config */
        $config = $di->get('config');

        $factory = (new ApiFactory(
            $assessmentDataSource,
            new ModuleChecker($config),
            $config,
            $databaseExternal
        ));

        /* These are the available options from which you can chooze
         */
        $subscriptions = $factory->generateAvailableSubscriptions();
        $eventTypes = \Schoolbox\Calendar\EventTypeMapper::getInstance()->findAll();

        /* Init $settings struct
         */
        $codec = new FilterCodec();

        /* This came out of the db
         *
         * Note that Scrub::text() on a json encoded string will inject chars that
         * prevent it from being json_decoded (will result in Syntax Error exception)
         */
        $settings = $codec->decode($configValue);

        /* phpliberty island
         */
        $page->setBlock($keyblock, 'calendarfilter_eventtype', 'calendarfilter_eventtype_block');
        $page->setVar('calendarfilter_eventtype_block', '');

        $page->setVar(
            'COMPONENT_CONFIG_CALENDARSUBSCRIBE_LINK',
            (
                // The permission checks here determine whether the link below is shown, and so must match the
                // permission checks on the linked-to page.  See www/adminv2/calendar.php.
                !$di->get('user')->isSuperuser() &&
                !$di->get('user')->hasServicePermission(UserAccess::SERVICE_CALENDAR, UserAccess::ACCESS_FULL)
            )
            ? ''
            : '<a href="/adminv2/calendar/Subscribe">Manage calendar subscriptions</a>'
        );

        foreach ($eventTypes as $eventType) {
            $page->setVar([
                'COMPONENT_CONFIG_CALENDARFILTER_VALUE'    => $eventType->getId(),
                'COMPONENT_CONFIG_CALENDARFILTER_NAME'     => $eventType->getType(),
                'COMPONENT_CONFIG_CALENDARFILTER_SELECTED' => \in_array($eventType->getId(), $settings['eventType'], true) ? 'checked="checked"' : '',
            ]);

            $page->parse('calendarfilter_eventtype_block', 'calendarfilter_eventtype', true);
        }

        $page->setBlock($keyblock, 'calendarfilter_source', 'calendarfilter_source_block');
        $page->setVar('calendarfilter_source_block', '');

        foreach ($subscriptions as $subscription) {
            $page->setVar([
                'COMPONENT_CONFIG_CALENDARFILTER_VALUE'    => $subscription->getId(),
                'COMPONENT_CONFIG_CALENDARFILTER_NAME'     => sprintf('%s (%s)', $subscription->getLabel(), $subscription->getDefaultLabel()),
                'COMPONENT_CONFIG_CALENDARFILTER_SELECTED' => \in_array($subscription->getId(), $settings['source'], false) ? 'checked="checked"' : '',
            ]);

            $page->parse('calendarfilter_source_block', 'calendarfilter_source', true);
        }
    }
}
