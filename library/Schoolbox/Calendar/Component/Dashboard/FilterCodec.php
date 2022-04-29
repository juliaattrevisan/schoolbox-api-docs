<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Component\Dashboard;

/**
 * Class FilterCodec
 *
 * We've never stored a json in a config value before; Scrub injects
 * stuff that causes json_decode to fail; for now, provide a special case
 * Not sure whether we worry too much about scrubbing Dashboard component
 * configs, but until I have a smarter answer, this will have to do for now
 */
class FilterCodec
{

    /**
     * DSN format
     *
     * e.g. eventType:1,2,3,4;source:5,6,7
     */
    private const DSN_FORMAT = '/eventType:([\d,]+)?;source:([\d,]+)?/';


    /**
     * Take a scrub:text()'d $dsn and bust it down into its parts
     * Scrub::text destroys json_encodes
     *
     * @param string|null $dsn
     * @return array
     */
    public function decode(?string $dsn): array
    {
        if ('' === (string)$dsn) {
            return $this->getDefaultPayload();
        }

        preg_match(self::DSN_FORMAT, $dsn, $matches);

        return [
            'eventType' => isset($matches[1]) && $matches[1] ? array_map('intval', explode(',', $matches[1])) : [],
            'source' => isset($matches[2]) && $matches[2] ? array_map('intval', explode(',', $matches[2])) : [],
        ];
    }


    /**
     * Encode a dsn string
     *
     * @param array $parts
     * @return string
     */
    public function encode(array $parts): string
    {
        return sprintf(
            'eventType:%s;source:%s',
            implode(',', array_filter($parts['eventType'] ?? [])),
            implode(',', array_filter($parts['source'] ?? []))
        );
    }


    /**
     * Return a default payload
     *
     * @return array
     */
    public function getDefaultPayload(): array
    {
        return [
            'eventType' => [],
            'source' => []
        ];
    }
}
