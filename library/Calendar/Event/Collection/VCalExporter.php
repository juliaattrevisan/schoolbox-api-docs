<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Collection;

use Calendar\Event\Type\EventBase;
use Sabre\VObject\Component\VCalendar;
use Config;

class VCalExporter implements IExporter
{

    /**
     * This will stream the vcal output as it parses over the event collection
     *
     * @param Renderer $renderer
     * @param string   $filename
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function export(Renderer $renderer, string $filename): void
    {
        header('Cache-Control: private');
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: filename=' . $filename . '.ics');


        $vCalendar = new VCalendar();
        $vCalendar->add('method', 'PUBLISH');
        $vCalendar->add('X-WR-CALNAME', Config::get('title') . ' Calendar');
        $vCalendar->add('X-WR-CALDESC', 'Calendar for ' . Config::get('school_name'));

        // Output base calendar, and output its start and end separately
        echo trim(str_replace('END:VCALENDAR', '', $vCalendar->serialize())) . "\r\n";

        $baseUrl = 'https://' . Config::get('site_hostname') . '.' . Config::get('site_domain');
        foreach ($renderer->getAllEvents() as $currentEvent) {
            echo $renderer->renderEvent($currentEvent, EventBase::RENDER_FORMAT_VCAL, null, $baseUrl);
        }

        echo "END:VCALENDAR";
    }
}
