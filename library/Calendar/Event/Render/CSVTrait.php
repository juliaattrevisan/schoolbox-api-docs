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
        /**
         * have a preference to use memory(in bytes), before we make use of the filesystem.
         */
        $fd = fopen('php://temp/maxmemory:100000', 'rw');

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

        /**
         * it's important to capture the byte length of the generated line each time
         * because it's reasonable to expect the size of a line can exceed 100k bytes
         */
        $lineByteLength = fputcsv($fd, $data);
        rewind($fd);

        /**
         * so that we don't accidentally truncate the string(which could remove trailing linefeeds)
         * and consequently join two otherwise separate lines together
         */
        return fread($fd, $lineByteLength);
    }

    /**
     * @param string $baseUrl
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }
}
