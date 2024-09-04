<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

use Alaress_Date_Format;
use Psr\Log\LoggerInterface;

/**
 * Composite index builder: builds indices from a list of builders.
 */
class CompositeBuilder implements IBuilder
{
    /**
     * @var array<IBuilder>
     */
    private array $builders;

    /**
     * Constructor
     *
     * @param IBuilder ...$builders A list of builders from which to compose
     *                              this builder.
     */
    public function __construct(IBuilder ...$builders)
    {
        $this->builders = $builders;
    }

    /**
     * Build updates for all builders in this composite.
     */
    public function buildUpdates(
        ?Alaress_Date_Format $fromDate,
        ?Alaress_Date_Format $toDate,
        ?object $subject,
    ): iterable {
        foreach ($this->builders as $builder) {
            foreach ($builder->buildUpdates($fromDate, $toDate, $subject) as $item) {
                yield $item;
            }
        }
    }

    /**
     * Build deletes for all builders in this composite.
     */
    public function buildDeletes(
        ?Alaress_Date_Format $fromDate,
        ?Alaress_Date_Format $toDate,
        ?object $subject,
    ): iterable {
        foreach ($this->builders as $builder) {
            foreach ($builder->buildDeletes($fromDate, $toDate, $subject) as $item) {
                yield $item;
            }
        }
    }

    /**
     * Set logger for all builders in this composite.
     */
    public function setLogger(LoggerInterface $logger): self
    {
        foreach ($this->builders as $i => $builder) {
            $this->builders[$i] = $builder->setLogger($logger);
        }
        return $this;
    }
}
