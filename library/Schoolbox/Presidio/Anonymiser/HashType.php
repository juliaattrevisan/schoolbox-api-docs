<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Presidio\Anonymiser;

/**
 * Enum representing valid hash types for the Hash anonymiser.
 */
enum HashType : string
{
    case Sha256 = 'sha256';
    case Sha512 = 'sha512';
}
