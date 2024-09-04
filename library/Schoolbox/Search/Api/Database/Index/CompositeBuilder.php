<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

use Alaress_Date_Format;

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
     * @inheritdoc
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
     * @inheritdoc
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
}
