<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Translator;

use Schoolbox\Learning\Moment\Evidence;

/**
 * Composite translator: takes multiple translators, and merges their results
 * together.
 *
 * Keys returned by translators later in the list will override keys returned by
 * translators earlier in the list.
 */
class Composite
{
    /**
     * The list of translators provided to this composite translator.
     *
     * @var array|callable[]
     */
    private array $translators;


    /**
     * Constructor
     *
     * @param callable ...$translators
     */
    public function __construct(callable ...$translators)
    {
        $this->translators = $translators;
    }


    /**
     * @param Evidence\Model $evidence
     * @return array
     */
    public function __invoke(Evidence\Model $evidence): array
    {
        $translations = [];
        foreach ($this->translators as $translator) {
            $translations[] = $translator($evidence);
        }
        return array_merge_recursive(...$translations);
    }
}
