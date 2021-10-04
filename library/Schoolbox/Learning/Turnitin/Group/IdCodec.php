<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Group;

/**
 * Create a unique string that distinguishes this schoolbox instance's
 * turnitin group identifiers from another instance's. Turnitin accounts
 * can be shared between staging and production environments, allowing
 * one environment to pollute the other environment by testing or fiddling
 * etc. since submission box ids can collide. Submission box 123 in the
 * staging environment isn't necessarily the same as in the production
 * environment, especially after a live->dev sync for testing.
 */
class IdCodec
{
    /**
     * A scheme for building group identifiers in turnitin
     *
     * Classically, it's the same as our submission box ids, but we have to distinguish
     * them from staging and production servers for a school since they'll share a
     * turnitin account. This means that activities conduced in staging might pollute
     * the live, and vice versa.
     *
     * @param int    $submissionBoxId
     * @param string $identifier
     * @return string
     */
    public function encode(int $submissionBoxId, string $identifier): string
    {
        return "{$submissionBoxId}-{$identifier}";
    }
}