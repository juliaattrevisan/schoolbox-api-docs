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
 * PHPStan rule for enforcing that PHPUnit test names match a given pattern, and
 * that non-tests do not.
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
    private const ERROR_MESSAGE_TEST_PREFIX_MISMATCH = 'Test case file name "%s" must start with "%s"';
    private const ERROR_MESSAGE_TEST_SUFFIX_MISMATCH = 'Test case file name "%s" must end with "%s"';

    private const ERROR_MESSAGE_NON_TEST_PREFIX_MISMATCH = 'Non-test case file name "%s" must not start with "%s"';
    private const ERROR_MESSAGE_NON_TEST_SUFFIX_MISMATCH = 'Non-test case file name "%s" must not end with "%s"';

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

        // Extract basename from file
        $filename = $reflectionClass->getFileName();
        if (!isset($filename)) {
            return [];
        }
        $basename = basename($filename);


        $hasPrefix     = isset($this->prefix);
        $hasSuffix     = isset($this->suffix);
        $matchesPrefix = $hasPrefix && str_starts_with($basename, $this->prefix);
        $matchesSuffix = $hasSuffix && str_starts_with($basename, $this->suffix);
        $messages      = [];

        // If a test case: filename must match prefix and suffix
        if ($reflectionClass->isSubclassOf(TestCase::class)) {
            if ($hasPrefix && !$matchesPrefix) {
                $messages[] = RuleErrorBuilder::message(
                    sprintf(self::ERROR_MESSAGE_TEST_PREFIX_MISMATCH, $basename, $this->prefix)
                )->build();
            }
            if ($hasSuffix && !$matchesSuffix) {
                $messages[] = RuleErrorBuilder::message(
                    sprintf(self::ERROR_MESSAGE_TEST_SUFFIX_MISMATCH, $basename, $this->suffix)
                )->build();
            }
            return $messages;
        }

        // If not a test case: filename must not match prefix and suffix
        if ($hasPrefix && $matchesPrefix) {
            $messages[] = RuleErrorBuilder::message(
                sprintf(self::ERROR_MESSAGE_NON_TEST_PREFIX_MISMATCH, $basename, $this->prefix)
            )->build();
        }
        if ($hasSuffix && $matchesSuffix) {
            $messages[] = RuleErrorBuilder::message(
                sprintf(self::ERROR_MESSAGE_NON_TEST_SUFFIX_MISMATCH, $basename, $this->suffix)
            )->build();
        }

        return $messages;
    }
}
