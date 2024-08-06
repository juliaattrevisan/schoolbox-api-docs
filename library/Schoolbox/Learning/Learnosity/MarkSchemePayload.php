<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

/**
 * Takes a Mark Scheme and any assorted Grade Group info
 * and compiles it into a minimal flat array struct
 */
class MarkSchemePayload
{
    /**
     * Constructor.
     */
    public function __construct()
    {
    }


    /**
     * Convert Mark Scheme and Grade Groups into an array
     *
     * Grade Groups are only relevant for the Letter Grade Mark Scheme;
     * if you've chosen some other mark scheme, Grade Groups will be empty.
     * An $assessment always knows about the last Grade Group that it saved,
     * even when you didn't use it.
     *
     * @return array
     */
    public function convert(): array
    {
        $struct = [];

        return $struct;
    }
}
