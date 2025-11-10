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
 * PHPStan rule for enforcing that the shebang line for PHP scripts is portable
 * between distributions.
 *
 * It must be `#!/usr/bin/env php` and not `#!/usr/bin/php`.
 */
class EnforcePortablePhpShebangLine implements Rule
{
    private const INVALID_SHEBANG_LINES = [
        '#!/usr/bin/php',
        '#!/usr/local/bin/php',
        '#!/bin/php',
    ];

    private const VALID_SHEBANG_LINE = '#!/usr/bin/env php';

    private const IDENTIFIER = 'phpShebang.portable';

    /**
     * @inheritdoc
     */
    public function getNodeType(): string
    {
        return Node\Stmt\InlineHTML::class;
    }

    /**
     * @inheritdoc
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof Node\Stmt\InlineHTML && $node->getLine() === 1) {
            foreach (self::INVALID_SHEBANG_LINES as $invalidShebangLine) {
                if (str_contains($node->value, $invalidShebangLine)) {
                    return [
                        RuleErrorBuilder::message(
                            sprintf(
                                'The PHP shebang line "%s" is not portable: use "%s" instead',
                                $invalidShebangLine,
                                self::VALID_SHEBANG_LINE,
                            )
                        )->identifier(self::IDENTIFIER)->build(),
                    ];
                }
            }
        }
        return [];
    }
}
