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
    private const SUFFIX = 'Test.php';

    private const ERROR_MESSAGE = 'PHPUnit test case file name "%s" must end with "%s"';

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
        // Not in class: this doesn't apply
        $reflectionClass = $scope->getClassReflection();
        if (!$reflectionClass) {
            return [];
        }

        // Not a test: this doesn't apply
        if (!$reflectionClass->isSubclassOf(TestCase::class)) {
            return [];
        }

        // Check the file name matches the suffix
        $filename = $reflectionClass->getFileName();
        if (str_ends_with($filename, self::SUFFIX)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(self::ERROR_MESSAGE, $filename, self::SUFFIX))->build(),
        ];
    }
}
