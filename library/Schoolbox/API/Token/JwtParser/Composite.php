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
     * @param LoggerInterface $log
     * @param IParser         ...$parsers
     */
    public function __construct(private LoggerInterface $log, IParser ...$parsers)
    {
        $this->parsers = $parsers;
    }

    /**
     * @inheritdoc
     */
    public function accepts(#[SensitiveParameter] string $jwt): bool
    {
        $this->log->info('Checking all parsers for whether they accept JWT');
        foreach ($this->parsers as $parser) {
            $this->log->debug(sprintf('Checking if parser "%s" accepts JWT', get_class($parser)));
            if ($parser->accepts($jwt)) {
                $this->log->info(sprintf('Parser "%s" accepts JWT: using this parser', get_class($parser)));
                return true;
            }
            $this->log->debug(sprintf('Parser "%s" does not accept JWT: checking next parser', get_class($parser)));
        }
        $this->log->info('No parsers accept JWT: thus, composite does not accept JWT');
        return false;
    }

    /**
     * @inheritdoc
     */
    public function decode(#[SensitiveParameter] string $jwt, mixed $keyOrKeySet, int $leeway): array
    {
        foreach ($this->parsers as $parser) {
            if ($parser->accepts($jwt)) {
                $this->log->info(sprintf('Parser "%s" accepts JWT: decoding', get_class($parser)));
                try {
                    return $parser->decode($jwt, $keyOrKeySet, $leeway);
                } catch (Exception\Rejected $e) {
                    $this->log->error(sprintf('Could not decode JWT (message: %s)', $e->getMessage()));
                    throw $e;
                }
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
                $this->log->info(sprintf('Parser "%s" accepts JWT: determining user', get_class($parser)));
                try {
                    $user = $parser->determineUser($jwt);
                } catch (Exception\Base $e) {
                    $this->log->error(sprintf('Could not determine user (message: %s)', $e->getMessage()));
                    throw $e;
                }
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
