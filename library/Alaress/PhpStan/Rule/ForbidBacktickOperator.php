<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\PhpStan\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan rule for enforcing that backticks are not used for executing shell
 * commands.
 *
 * These are going to be very hard to find with a casual search: better to just
 * mandate the use of `shell_exec`.
 */
class ForbidBacktickOperator implements Rule
{
    private const IDENTIFIER = 'backtick.forbidden';

    /**
     * @inheritdoc
     */
    public function getNodeType(): string
    {
        return Node\Expr\ShellExec::class;
    }

    /**
     * @inheritdoc
     */
    public function processNode(Node $node, Scope $scope): array
    {
        return [
            RuleErrorBuilder::message('The backtick operator is forbidden: use shell_exec instead')
                ->identifier(self::IDENTIFIER)
                ->build(),
        ];
    }
}
