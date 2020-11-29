<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Translator\ModelToArray;

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
     * @param object $model
     * @return array
     */
    public function __invoke(object $model): array
    {
        $translations = [];
        foreach ($this->translators as $translator) {
            $translations[] = $translator($model);
        }

        // array_merge_recursive doesn't do what I expected:
        // if it finds the same key in two arrays, it will preserve both in an
        // array.
        // array_merge_recursive(['a' => 'b'], ['a' => 'c']) returns ["a" => ["b", "c", ],]
        return array_replace_recursive(...$translations);
    }
}
