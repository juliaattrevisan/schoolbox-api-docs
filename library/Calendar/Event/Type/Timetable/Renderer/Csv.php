<?php
/**
 * Calendar_Event_Type_Day_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Calendar\Event\Type\Timetable\Renderer;


class Csv extends Base {
    /**
     * Render this event out as CSV data
     *
     */
    public function render()
    {
        $fd = fopen('php://temp/maxmemory:10000', 'rw');

        $data = array(
            $this->getEvent()->getStartTime()->getLocalShort(),
            $this->getEvent()->getAllday() ? '' : $this->getEvent()->getStartTime()->getUsingFormat('H:i'),
            $this->getEvent()->getEndTime()->getLocalShort(),
            $this->getEvent()->getAllday() ? '' : $this->getEvent()->getEndTime()->getUsingFormat('H:i'),
            $this->getEvent()->getAllday() ? 1 : 0,
            $this->getEvent()->getTitle(),
            $this->getEvent()->getDetail(),
            $this->getEvent()->getLocation(),
            $this->getEvent()->getType(),
            $this->getEvent()->getPublishDate()->getLocalShort(),
            $this->getEvent()->canAttend() ? 1 : 0,
        );

        fputcsv($fd, $data);
        rewind($fd);
        $row = fread($fd, 10000);

        return $row;
    }

}

