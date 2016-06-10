<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Calendar\Event\Render;

/**
 * Default CSV rendering for all types of Calendar events.
 * 
 * @package Calendar\Event\Render
 */
trait CSVTrait
{
    /**
     * Render this event out as CSV data
     */
    public function render()
    {
        $fd = fopen('php://temp/maxmemory:10000', 'rw');

        $event = $this->getEvent();

        $data = array(
            $event->getStartTime()->getLocalShort(),
            $event->getAllday() ? '' : $event->getStartTime()->getUsingFormat('H:i'),
            $event->getEndTime()->getLocalShort(),
            $event->getAllday() ? '' : $event->getEndTime()->getUsingFormat('H:i'),
            $event->getAllday() ? 1 : 0,
            $event->getTitle(),
            $event->getDetail(),
            $event->getLocation(),
            $event->getType(),
            $event->getPublishDate()->getLocalShort(),
            $event->canAttend() ? 1 : 0,
        );

        fputcsv($fd, $data);
        rewind($fd);
        $row = fread($fd, 10000);

        return $row;
    }
}