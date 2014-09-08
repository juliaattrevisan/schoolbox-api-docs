<?php

/**
 * Lister
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Event_Type_Renderer_Lister extends Calendar_Event_Type_Renderer_Abstract
{

    /**
     * Classes to be used as filter selectors
     *
     * @param $currentEvent
     * @return array|string
     */
    private function determineSelectors($currentEvent)
    {
        $selectors = array(
            'type' . $currentEvent->getType()
        );

        /* Merge levels
         */
        $allLevels = explode(' ', $currentEvent->getDerivedEventLevel());
        $selectors = array_merge($selectors, $allLevels);

        /* Merge campuses
         */
        $allCampuses = explode(',', $currentEvent->getCampusList());

        foreach ($allCampuses as $currentCampus) {
            $selectors[] = 'campus' . trim($currentCampus);
        }

        /* Properly trim out spaces and stuff
         */
        foreach ($selectors as &$currentSelector) {
            $currentSelector = trim($currentSelector);
        }

        $selectors = array_filter(array_unique($selectors));

        return implode(' ', $selectors);
    }

    /**
     * Render this event
     *
     * @param Calendar_Event_Type_Abstract $currentEvent
     * @return Sabre\VObject\Component\Vevent
     */
    public function visit($currentEvent)
    {
        if ($currentEvent->getPublishDate()->getUnixTimestamp() > $_SERVER['REQUEST_TIME']) {
            $cssClass = 'translucent';
        } else {
            $cssClass = '';
        }

        $this->getPage()->setVar(
            array(
                'EVENT_START' => $currentEvent->getStartTime()->getTimeOnly(),
                'EVENT_END' => $currentEvent->getEndTime()->getTimeOnly(),
                'EVENT_TITLE' => Scrub::text($currentEvent->getTitle()),
                'EVENT_SELECTORS' => $this->determineSelectors($currentEvent),
                'EVENT_CLASS' => $cssClass,
                'EVENT_COLOUR' => $currentEvent->getColour(),
            )
        );

        if ($currentEvent->getAllday()) {
            $this->getPage()->parse('event_allday_block', 'event_allday');

            $this->getPage()->setVar(
                array(
                    'event_timed_block' => ''
                )
            );
        } else {
            $this->getPage()->parse('event_timed_block', 'event_timed');

            $this->getPage()->setVar(
                array(
                    'event_allday_block' => '',
                )
            );
        }

        $this->getPage()->parse('event_block', 'event', true);

        /* Clear children after parsing out the parent
         */
        $this->getPage()->setVar(
            array(
                'event_allday_block' => '',
                'event_timed_block' => ''
            )
        );
    }
} 