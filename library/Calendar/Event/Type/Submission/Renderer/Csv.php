<?php
/**
 * Calendar_Event_Type_Day_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Calendar\Event\Type\Submission\Renderer;

class Csv extends Base{

    /**
     * Render this event out as CSV data
     */
    public function render()
    {
        if(!$this->isVisible()){
            return '';
        }

        $fd = fopen('php://temp/maxmemory:10000', 'rw');


        $data = array($this->getEvent()->getStartTime()->getLocalShort(),
                      $this->getEvent()->getStartTime()->getTimeOnly(),
                      $this->getEvent()->getEndTime()->getLocalShort(),
                      $this->getEvent()->getEndTime()->getTimeOnly(),
                      $this->getEvent()->getAllday(),
                      $this->getEvent()->getTitle(),
                      $this->getEvent()->getDetail(),
                      '',
                      $this->getEvent()->getEventType(),
                      $this->getEVent()->getPublishDate()->getLocalShort(),);

        fputcsv($fd, $data);
        rewind($fd);
        $row = fread($fd, 10000);

        return $row;
    }
}

