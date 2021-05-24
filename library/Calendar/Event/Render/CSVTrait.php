<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Render;

use Calendar\Event\Type\Day\Event;

/**
 * Default CSV rendering for all types of Calendar events.
 *
 * @package Calendar\Event\Render
 */
trait CSVTrait
{
    /**
     * @var string
     */
    private $baseUrl;

    /**
     * Render this event out as CSV data
     * @return string
     */
    public function render(): string
    {
        $fd = fopen('php://temp/maxmemory:10000', 'rw');

        /** @var Event $event */
        $event = $this->getEvent();
        $data  = [
            $event->getStartTime()->getLocalShort(),
            $event->getAllday() ? '' : $event->getStartTime()->getUsingFormat('H:i'),
            $event->getEndTime()->getLocalShort(),
            $event->getAllday() ? '' : $event->getEndTime()->getUsingFormat('H:i'),
            $event->getAllday() ? 1 : 0,
            $event->getTitleForExport(),
            $event->getDetail() ? \Scrub::htmlAbsoluteUri($event->getDetail(), $this->baseUrl) : '',
            $event->getLocation(),
            $event->getType(),
            empty($event->getPublishDate()) ? '' : $event->getPublishDate()->getLocalShort(),
            $event->canAttend() ? 1 : 0,
        ];

        fputcsv($fd, $data);
        rewind($fd);
        return fread($fd, 10000);
    }

    /**
     * @param string $baseUrl
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }
}
