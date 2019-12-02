<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Mock;

use Faker\Generator;

/**
 * Search mock result builder.
 */
class ResultBuilder
{
    /**
     * @var Generator|null
     */
    private $faker;


    /**
     * Constructor
     *
     * @param Generator|null $faker
     */
    public function __construct(?Generator $faker)
    {
        $this->faker = $faker;
    }


    /**
     * Generates a mock search result.
     *
     * @return array
     */
    public function generate(): array
    {
        // Options:
        // errorChance?
        // searchTerm
        // date from/to
        // creator
        // cursor
        // allowed types
        return [];
    }
}
