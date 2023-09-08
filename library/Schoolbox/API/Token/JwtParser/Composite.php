<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Schoolbox\Application\Exception\NotFound;
use Schoolbox\User;
use SensitiveParameter;

/**
 * Composite parser: takes one or more parsers, and passes calls to `accepts`
 * and `determineUser` through to each one in turn until a parser accepts
 * the JWT.
 */
class Composite implements IParser
{
    /**
     * A set of parsers, each to be checked in sequence.
     *
     * @var IParser[]
     */
    private array $parsers;

    /**
     * @see IParser::bypassIpTracking()
     */
    public bool $bypassIpTracking = false;

    /**
     * Constructor
     *
     * @param IParser ...$parsers
     */
    public function __construct(IParser ...$parsers)
    {
        $this->parsers = $parsers;
    }

    /**
     * @inheritdoc
     */
    public function accepts(#[SensitiveParameter] string $jwt): bool
    {
        foreach ($this->parsers as $parser) {
            if ($parser->accepts($jwt)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function determineUser(#[SensitiveParameter] string $jwt): User
    {
        foreach ($this->parsers as $parser) {
            if ($parser->accepts($jwt)) {
                $user = $parser->determineUser($jwt);
                $this->bypassIpTracking = $parser->bypassIpTracking();
                return $user;
            }
        }
        throw new NotFound();
    }

    /**
     * @inheritDoc
     */
    public function bypassIpTracking(): bool
    {
        return $this->bypassIpTracking;
    }
}
