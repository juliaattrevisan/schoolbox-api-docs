<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\Instance;

use Schoolbox\Url\Uri;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\PlatformInstanceClaim;
use Schoolbox\Instance;

/**
 * Factory for claims about the Schoolbox instance.
 */
class ClaimFactory
{
    /**
     * Instance data repository.
     */
    private Instance $instance;

    /**
     * Constructor
     *
     * @param Instance $instance
     */
    public function __construct(Instance $instance)
    {
        $this->instance = $instance;
    }

    /**
     * Build a platform instance claim from the given instance.
     *
     * @return PlatformInstanceClaim
     */
    public function build(): PlatformInstanceClaim
    {
        // TODO base URL should be constructable from instance
        $host    = trim("{$this->instance->getHostname()}.{$this->instance->getDomain()}", '. ');
        $baseUrl = (new Uri())->withScheme('https')->withHost($host);
        return new PlatformInstanceClaim(
            $this->instance->getId(),
            null,
            null,
            null,
            (string)$baseUrl,
            $this->instance::APPLICATION_NAME,
            $this->instance->getVersion(),
        );
    }
}
