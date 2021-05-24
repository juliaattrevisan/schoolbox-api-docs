<?php

/**
 * Collection_Exporter
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Collection;

use Calendar\Event\Type\EventBase;
use Config;

class CSVExporter implements IExporter
{

    /**
     * This will stream the vcal output as it parses over the event collection
     *
     * @param Renderer $renderer
     * @param string   $filename
     * @throws \Exception
     */
    public function export(Renderer $renderer, string $filename): void
    {
        header('Cache-Control: private');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: filename=' . $filename . '.csv');

        $csvFormat = [
            'start_date',
            'start_time',
            'finish_date',
            'finish_time',
            'all_day',
            'name',
            'detail',
            'location',
            'event_type',
            'publish_date',
            'attendance',
        ];

        // Output base calendar, and output its start and end separately
        echo implode(',', $csvFormat) . "\n";

        $baseUrl = 'https://' . Config::get('site_hostname') . '.' . Config::get('site_domain');

        foreach ($renderer->getAllEvents() as $currentEvent) {
            echo $renderer->renderEvent($currentEvent, EventBase::RENDER_FORMAT_CSV, null, $baseUrl);
        }
    }
}
