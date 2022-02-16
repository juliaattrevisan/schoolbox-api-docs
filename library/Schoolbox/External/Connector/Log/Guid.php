<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Log;

/**
 * Generate a guid to identify things uniquely
 */
class Guid
{

    /**
     * Generate a microsofty style guid
     *
     * @return string
     */
    public function generate(): string
    {
        $uuid = md5(uniqid((string)mt_rand(), true));

        return substr($uuid, 0, 8) . "-" .
            substr($uuid, 8, 4) . "-" .
            substr($uuid, 12, 4) . "-" .
            substr($uuid, 16, 4) . "-" .
            substr($uuid, 20, 12);
    }
}
