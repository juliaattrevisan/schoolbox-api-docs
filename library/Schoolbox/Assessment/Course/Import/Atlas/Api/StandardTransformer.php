<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Karriere\JsonDecoder\Bindings\CallbackBinding;
use Karriere\JsonDecoder\ClassBindings;
use Karriere\JsonDecoder\Transformer;
use Schoolbox\Assessment\Course\Import\Atlas\Api\StandardTransformer\IStrategy;

/**
 * Transformer definition for JSON standard responses to PHP objects.
 */
class StandardTransformer implements Transformer
{
    /**
     * @var IStrategy[]
     */
    private $strategies;


    /**
     * Constructor
     *
     * @param IStrategy ...$strategies
     */
    public function __construct(IStrategy ...$strategies)
    {
        $this->strategies = $strategies;
    }


    /**
     * Register bindings for this class.
     *
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param ClassBindings $classBindings
     */
    public function register(ClassBindings $classBindings): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $classBindings->register(
            new CallbackBinding(
                'title',
                function ($row) {
                    // Extract title out of parsed raw text
                    return $this->parseStandard($row['rawText'])['title'];
                }
            )
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $classBindings->register(
            new CallbackBinding(
                'code',
                function ($row) {
                    // Unfortunately I must parse code twice,
                    // I can't map two attributes in one binding
                    return $this->parseStandard($row['rawText'])['code'];
                }
            )
        );
    }


    /**
     * @return string
     */
    public function transforms(): string
    {
        return Standard::class;
    }


    /**
     * Parses a string standard into something renderable.
     *
     * This is required because Atlas stores curriculum codes as part of a
     * string value.
     *
     * @param string $standard
     * @return array
     */
    private function parseStandard(string $standard): array
    {
        foreach ($this->strategies as $strategy) {
            $result = $strategy->parseStandard($standard);
            if ($result) {
                return $result;
            }
        }

        // Fallback: title is standard, code is null
        return [
            'title' => $standard,
            'code'  => null,
        ];
    }
}
