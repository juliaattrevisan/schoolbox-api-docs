<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\Codec;

class Metadata
{
    /**
     * Encodes an array into a serialised format for the db to persist
     *
     * @param array $metadata
     * @return string
     */
    public function encode(array $metadata): string
    {
        return json_encode($metadata);
    }


    /**
     * Accept both the json-encoded metadata and string formats
     * for interchangeability. You could be working with either the
     * mapper-decoded version or the db-encoded versions...
     *
     * @param array|string $metadata
     * @return array
     * @throws \JsonException
     */
    public function decode(array|string $metadata): array
    {
        $deserialized = $metadata;

        if (is_string($metadata)) {
            $deserialized = json_decode($deserialized, true, 512, JSON_THROW_ON_ERROR);
        }

        return $deserialized;
    }
}
