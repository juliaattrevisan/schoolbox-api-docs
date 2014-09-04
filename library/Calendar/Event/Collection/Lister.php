<?php
/**
 * Lister
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Calendar_Event_Collection_Lister extends Calendar_Event_Collection_Renderer {

    /**
     * Render all the events in a list format
     */
    public function renderAll() {
        $this->getPage()->setFile(TEMPLATE_content, 'calendar/list.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'date', 'date_block');
        $this->getPage()->setBlock('date', 'event', 'event_block');

        $renderer = new Calendar_Event_Type_Renderer_Lister($this->getPage());

        $lastDate = 0;

        foreach ($this->getAllEvents() as $currentEvent) {
            $currentDate = $currentEvent->getStartTime()->getDatabaseDate();

            if ($lastDate != $currentDate) {
                $this->getPage()->parse('date_block', 'date', true);

                /* Clear children after parsing out the parent
                 */
                $this->getPage()->setVar(array(
                    'event_block' => ''
                ));

                $lastDate = $currentDate;
            }

            $this->getPage()->setVar(array(
                'EVENT_DATE' => $currentEvent->getStartTime()->getShort()
            ));

            $currentEvent->accept($renderer);
        }
    }
}