<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\Instance;

use Psr\Http\Message\UriInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\PlatformInstanceClaim;
use Schoolbox\Instance;

/**
 * Factory for claims about the Schoolbox instance.
 */
class ClaimFactory
{
    /**
     * Constructor
     *
     * @param Instance     $instance Instance data repository.
     * @param UriInterface $baseUrl  Base URL for this instance.
     */
    public function __construct(private Instance $instance, private UriInterface $baseUrl)
    {
    }

    /**
     * Build a platform instance claim from the given instance.
     *
     * @return PlatformInstanceClaim
     */
    public function build(): PlatformInstanceClaim
    {
        return new PlatformInstanceClaim(
            $this->instance->getId(),
            null,
            null,
            null,
            $this->baseUrl->__toString(),
            $this->instance::APPLICATION_NAME,
            $this->instance->getVersion(),
        );
    }
}
