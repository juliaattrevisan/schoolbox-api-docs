<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Psr\Log\LoggerInterface;
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
     * @param LoggerInterface $logger
     * @param IParser         ...$parsers
     */
    public function __construct(private LoggerInterface $logger, IParser ...$parsers)
    {
        $this->parsers = $parsers;
    }

    /**
     * @inheritdoc
     */
    public function accepts(#[SensitiveParameter] string $jwt): bool
    {
        $this->logger->info('Checking all parsers for whether they accept JWT');
        foreach ($this->parsers as $parser) {
            $this->logger->debug(sprintf('Checking if parser "%s" accepts JWT', get_class($parser)));
            if ($parser->accepts($jwt)) {
                $this->logger->info(sprintf('Parser "%s" accepts JWT: using this parser', get_class($parser)));
                return true;
            }
            $this->logger->debug(sprintf('Parser "%s" does not accept JWT: checking next parser', get_class($parser)));
        }
        $this->logger->info('No parsers accept JWT: thus, composite does not accept JWT');
        return false;
    }

    /**
     * @inheritdoc
     */
    public function decode(#[SensitiveParameter] string $jwt, mixed $keyOrKeySet, int $leeway): array
    {
        foreach ($this->parsers as $parser) {
            if ($parser->accepts($jwt)) {
                $this->logger->info(sprintf('Parser "%s" accepts JWT: decoding', get_class($parser)));
                return $parser->decode($jwt, $keyOrKeySet, $leeway);
            }
        }
        throw new Exception\Rejected('No parser in this object accepts this JWT');
    }

    /**
     * @inheritdoc
     */
    public function determineUser(#[SensitiveParameter] string $jwt): User
    {
        foreach ($this->parsers as $parser) {
            if ($parser->accepts($jwt)) {
                $this->logger->info(sprintf('Parser "%s" accepts JWT: determining user', get_class($parser)));
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
