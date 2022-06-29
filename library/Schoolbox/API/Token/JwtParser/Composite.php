<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Schoolbox\User;

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
    public function accepts(array $claims): bool
    {
        foreach ($this->parsers as $parser) {
            if ($parser->accepts($claims)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function determineUser(string $jwt, array $claims): ?User
    {
        foreach ($this->parsers as $parser) {
            if ($parser->accepts($claims)) {
                return $parser->determineUser($jwt, $claims);
            }
        }
        return null;
    }
}
