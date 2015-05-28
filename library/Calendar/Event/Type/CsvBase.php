<?php
/**
 * Calendar_Event_Type_Exporter_VObject
 *
 * Like Calendar_Event_Type_Exporter but works with the
 * \Sabre\VObject\Component\VCalendar class instead.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Calendar\Event\Type;

use Html2Text\Html2Text;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Property\ICalendar;
use DateTimeZone;
use DateInterval;
use DateTime;


class CsvBase extends RendererBase
{
    /**
     * Render this event out as CSV data
     *
     */
    public function render()
    {
        $fd = fopen('php://temp/maxmemory:10000', 'rw');

        $data = array($this->getEvent()->getStartTime()->getLocalShort(),
            $this->getEvent()->getAllday() ? '' : $this->getEvent()->getStartTime()->getTimeOnly(),
            $this->getEvent()->getEndTime()->getLocalShort(),
            $this->getEvent()->getAllday() ? '' : $this->getEvent()->getEndTime()->getTimeOnly(),
            $this->getEvent()->getAllday(),
            $this->getEvent()->getTitle(),
            $this->getEvent()->getDetail(),
            $this->getEvent()->getLocation(),
            $this->getEvent()->getType(),
            $this->getEVent()->getPublishDate()->getLocalShort(),);

        fputcsv($fd, $data);
        rewind($fd);
        $row = fread($fd, 10000);

        return $row;
    }
}
