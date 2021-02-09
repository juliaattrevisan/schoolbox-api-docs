<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth\JwtVerifierFactory;

use Okta\JwtVerifier\JwtVerifier;

class Composite implements IFactory
{
    /**
     * The factories which may build a verifier: `build` will return the
     * verifier created by the first factory in this list to create one.
     *
     * @var IFactory[]
     */
    private array $factories;

    /**
     * Constructor
     *
     * @param IFactory ...$factories
     */
    public function __construct(IFactory ...$factories)
    {
        $this->factories = $factories;
    }

    /**
     * @inheritdoc
     */
    public function build(string $issuer, string $clientId, int $leeway): ?JwtVerifier
    {
        foreach ($this->factories as $factory) {
            $verifier = $factory->build($issuer, $clientId, $leeway);
            if ($verifier) {
                return $verifier;
            }
        }
        return null;
    }
}
