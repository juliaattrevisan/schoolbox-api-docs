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
        new \Alaress_Probe($value);
        return json_encode($value, JSON_THROW_ON_ERROR);
    }


    /**
     * @param SitePage                          $page
     * @param                                   $key
     * @param                                   $keyblock
     * @param                                   $configValue
     * @param Component_ControllerAbstract|null $component
     * @return void
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        /* These are the available options from which you can chooze
         */
        $eventTypes = (new EventType(\Schoolbox\Calendar\EventTypeMapper::getInstance()))->generate();
        $sources = (new SourceList(\Calendar_Api_Config_Mapper::getInstance(), new \Calendar\Api\ModuleChecker(\Schoolbox\Config\Loader::getInstance())))->generate();

        /* This came out of the db
         */
        $settings = [
            'eventType' => [],
            'sources' => []
        ];

//        [
//            'sources' => [1,2,3],
//            'eventType' => [4,5,6]
//        ];

        if ('' !== (string)$configValue) {
            $settings = json_decode($configValue);
        }

        /* phpliberty island
         */
        $page->setBlock($keyblock, 'calendarfilter_eventtype', 'calendarfilter_eventtype_block');
        $page->setVar('calendarfilter_eventtype_block', '');

        foreach ($eventTypes->getData() as $eventType) {
            /* TODO Decide if this eventType was selected from persisted data
             */

            $page->setVar([
                'COMPONENT_CONFIG_CALENDARFILTER_VALUE'    => $eventType['id'],
                'COMPONENT_CONFIG_CALENDARFILTER_NAME'     => $eventType['name'],
                'COMPONENT_CONFIG_CALENDARFILTER_SELECTED' => ''//in_array($eventType['id'], $settings['eventType'], true) ? 'checked="checked"' : '',
            ]);

            $page->parse('calendarfilter_eventtype_block', 'calendarfilter_eventtype', true);
        }

        $page->setBlock($keyblock, 'calendarfilter_source', 'calendarfilter_source_block');
        $page->setVar('calendarfilter_source_block', '');

        foreach ($sources->getData() as $source) {
            /* TODO Decide if this source was selected from persisted data
             */

            $page->setVar([
                'COMPONENT_CONFIG_CALENDARFILTER_VALUE'    => $source['id'],
                'COMPONENT_CONFIG_CALENDARFILTER_NAME'     => $source['name'],
                'COMPONENT_CONFIG_CALENDARFILTER_SELECTED' => ''//in_array($source['id'], $settings['source'], true) ? 'checked="checked"' : '',
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

