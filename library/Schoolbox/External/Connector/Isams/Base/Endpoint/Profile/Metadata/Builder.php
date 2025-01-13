<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Metadata;

class Builder
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }


    /**
     * Builds profile specific metadata such as photo ids etc.
     *
     * @param array $profileInstance
     * @return array
     */
    public function build(array $profileInstance): array
    {
        /* Each event contains this additional metadata struct,
         * for extra info that you might need later
         */
        return [
            'profile' => [
                'sisPhotoId' => $profileInstance['profile']['sisPhotoId'] ?? null
            ]
        ];
    }
}
