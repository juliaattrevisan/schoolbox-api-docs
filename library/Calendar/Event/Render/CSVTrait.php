<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Render;

use Calendar\Event\Type\Day\Event;
use Calendar\Event\Type\EventInterface;

/**
 * Default CSV rendering for all types of Calendar events.
 *
 * @package Calendar\Event\Render
 */
trait CSVTrait
{
    /**
     * @var \HTMLPurifier
     */
    private $htmlPurifier;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $cacheDir;

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
            $event->getTitle(),
            $this->getHtmlPurifier()->purify($event->getDetail()),
            $event->getLocation(),
            $event->getType(),
            empty($event->getPublishDate()) ? '' : $event->getPublishDate()->getLocalShort(),
            $event->canAttend() ? 1 : 0,
        ];

        fputcsv($fd, $data);
        rewind($fd);
        $row = fread($fd, 10000);

        return $row;
    }

    /**
     * @return \HTMLPurifier
     */
    protected function getHtmlPurifier(): \HTMLPurifier
    {
        if (!$this->htmlPurifier) {
            $htmlPurifierConfig = \HTMLPurifier_Config::createDefault();
            $htmlPurifierConfig->set('Cache.SerializerPath', $this->cacheDir);
            $htmlPurifierConfig->set('URI.Base', $this->baseUrl);
            $htmlPurifierConfig->set('URI.MakeAbsolute', true);
            $this->htmlPurifier = new \HTMLPurifier($htmlPurifierConfig);
        }
        return $this->htmlPurifier;
    }

    /**
     * @param string $baseUrl
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @param string $cacheDir
     */
    public function setCacheDir(string $cacheDir): void
    {
        $this->cacheDir = $cacheDir;
    }
}
