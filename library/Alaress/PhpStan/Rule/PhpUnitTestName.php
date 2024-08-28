<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\PhpStan\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\TestCase;

/**
 * PHPStan rule for enforcing PHPUnit test names match a given pattern.
 *
 * Necessary due to PHPUnit 11 raising errors if any class in a suite does
 * not extend its TestCase class, and there is no way to disable this besides
 * the `prefix` or `suffix` tags attributes on the test suite's `<directory>`
 * configuration.
 *
 * The only way to be sure that a test case is included in the suite is to
 * enforce that its name matches the same pattern as the suite's configuration.
 */
class PhpUnitTestName implements Rule
{
    private const ERROR_MESSAGE_PREFIX_MISMATCH = 'PHPUnit test case file name "%s" must start with "%s"';
    private const ERROR_MESSAGE_SUFFIX_MISMATCH = 'PHPUnit test case file name "%s" must end with "%s"';

    /**
     * Constructor
     *
     * @param string|null $prefix If provided, the file name must start with this value.
     * @param string|null $suffix If provided, the file name must end with this value.
     */
    public function __construct(private ?string $prefix = null, private ?string $suffix = null)
    {
    }

    /**
     * @inheritdoc
     */
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @inheritdoc
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // No prefix or suffix: no rules to class name, thus cannot test
        if (!isset($this->prefix) && !isset($this->suffix)) {
            return [];
        }

        // Not in class: this doesn't apply
        $reflectionClass = $scope->getClassReflection();
        if (!$reflectionClass) {
            return [];
        }

        // Not a test: this doesn't apply
        if (!$reflectionClass->isSubclassOf(TestCase::class)) {
            return [];
        }

        // Extract basename from file
        $filename = $reflectionClass->getFileName();
        if (!isset($filename)) {
            return [];
        }
        $basename = basename($filename);

        // Test prefix
        if ($this->prefix && !str_starts_with($basename, $this->prefix)) {
            return [
                RuleErrorBuilder::message(
                    sprintf(self::ERROR_MESSAGE_PREFIX_MISMATCH, $basename, $this->prefix)
                )->build(),
            ];
        }

        // Test suffix
        if ($this->suffix && !str_ends_with($basename, $this->suffix)) {
            return [
                RuleErrorBuilder::message(
                    sprintf(self::ERROR_MESSAGE_SUFFIX_MISMATCH, $basename, $this->suffix)
                )->build(),
            ];
        }

        return [];
    }
}
