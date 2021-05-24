<?php

/**
 * Null
 *
 * Used when no meta tags/opengraph policy used
 * LIKE ON GOOGLE NEXUS.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Metadata;

class NullMetadata extends Metadata
{

    /**
     * Fetch information out of the meta tags
     *
     * @return mixed
     */
    public function fetchMetadata()
    {
        return $this;
    }
}
