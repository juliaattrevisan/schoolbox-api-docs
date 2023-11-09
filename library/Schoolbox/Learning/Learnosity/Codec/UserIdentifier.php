<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Codec;

/**
 * Generates an instance-specific identifier that can be used in a global
 * context, because learnosity puts everyone's data in the same place
 */
class UserIdentifier
{
    /**
     * Take an instance id and a user id, and make something school-specific
     * out of it. One day it might not be this user id, but some other
     * identifying value like their hash or token or something
     * 
     * @param string $instanceId
     * @param string|int $userId
     * @return string
     */
    public function generate(string $instanceId, string|int $userId): string
    {
        return sprintf(
            '%s_%s',
            $instanceId,
            $userId
        );
    }
}
