<?php

/**
  * @copyright   Copyright 2025, Schoolbox Pty Ltd
  * @link        https://schoolbox.education/
  */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Metadata;

class Reader
{
    /**
     * Extract the sisPhotoId from the metadata
     *
     * @param array|string $metadata
     * @return string|null
     * @throws \JsonException
     */
    public function getProfileSisPhotoId(array|string $metadata): ?string
    {
        return $this->deserialize($metadata)['profile']['sisPhotoId'] ?? null;
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
    private function deserialize(array|string $metadata): array
    {
        $deserialized = $metadata;

        if (is_string($metadata)) {
            $deserialized = json_decode($deserialized, true, 512, JSON_THROW_ON_ERROR);
        }

        return $deserialized;
    }
}
