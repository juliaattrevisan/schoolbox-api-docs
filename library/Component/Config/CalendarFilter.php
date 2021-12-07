<?php

use Schoolbox\Calendar\Config\EventType;
use Schoolbox\Calendar\Config\Filter;
use Schoolbox\Calendar\Config\SourceList;

/**
 * These are the checkbox selections that superusers can tick
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
     * @param                              $value
     * @param Component_ControllerAbstract $controller
     * @return false|mixed|string
     * @throws JsonException
     */
    public function dehydrate($value, Component_ControllerAbstract $controller)
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }


    /**
     * @param SitePage                          $page
     * @param                                   $key
     * @param                                   $keyblock
     * @param                                   $configValue
     * @param Component_ControllerAbstract|null $component
     * @return void
     * @throws JsonException
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        /* These are the available options from which you can chooze
         */
        $eventTypes = (new EventType(\Schoolbox\Calendar\EventTypeMapper::getInstance()))->generate();
        $sources = (new SourceList(\Calendar_Api_Config_Mapper::getInstance(), new \Calendar\Api\ModuleChecker(\Schoolbox\Config\Loader::getInstance())))->generate();

        /* Init $settings struct
         */
        $settings = [
            'eventType' => [],
            'sources' => [],
        ];

        /* This came out of the db
         *
         * Note that Scrub::text() on a json encoded string will inject chars that
         * prevent it from being json_decoded (will result in Syntax Error exception)
         */
        if ('' !== (string)$configValue) {
            $settings = json_decode($configValue, true, 512, JSON_THROW_ON_ERROR);
        }

        /* phpliberty island
         */
        $page->setBlock($keyblock, 'calendarfilter_eventtype', 'calendarfilter_eventtype_block');
        $page->setVar('calendarfilter_eventtype_block', '');

        foreach ($eventTypes->getData() as $eventType) {
            $page->setVar([
                'COMPONENT_CONFIG_CALENDARFILTER_VALUE'    => $eventType['id'],
                'COMPONENT_CONFIG_CALENDARFILTER_NAME'     => $eventType['name'],
                'COMPONENT_CONFIG_CALENDARFILTER_SELECTED' => $settings['eventType'][$eventType['id']] ? 'checked="checked"' : '',
            ]);

            $page->parse('calendarfilter_eventtype_block', 'calendarfilter_eventtype', true);
        }

        $page->setBlock($keyblock, 'calendarfilter_source', 'calendarfilter_source_block');
        $page->setVar('calendarfilter_source_block', '');

        foreach ($sources->getData() as $source) {
            $page->setVar([
                'COMPONENT_CONFIG_CALENDARFILTER_VALUE'    => $source['id'],
                'COMPONENT_CONFIG_CALENDARFILTER_NAME'     => $source['name'],
                'COMPONENT_CONFIG_CALENDARFILTER_SELECTED' => $settings['source'][$source['id']] ? 'checked="checked"' : '',
            ]);

            $page->parse('calendarfilter_source_block', 'calendarfilter_source', true);
        }


//        /* TODO Get these from the component config
//         */
//        $persistedFilters = null;
//
//        /* Shank them into the filter class which does filter things
//         */
//        $filters = (new Filter($persistedFilters))->generate(
//            new \Schoolbox\Collection(),
//            new \Schoolbox\Collection(),
//            $eventType->getData(),
//            $sourceList->getData()
//        );
//
//        new \Alaress_Probe($eventType->getData());
//
//        $page->setVar('COMPONENT_CONFIG_NAME', $configValue);
    }
}

