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

    public function renderAll() {
        $this->getPage()->setFile(TEMPLATE_content, 'calendar/list.html');



        $renderer = new Calendar_Event_Type_Renderer_Lister($this->getPage());

        foreach ($this->getAllEvents() as $currentEvent) {
            $currentEvent->accept($renderer);
        }
    }
}