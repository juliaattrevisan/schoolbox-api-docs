<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule\Test;

class CanViewAssessableTestExternalUser
{
    public function loadChildren(array $parents): array
    {
        return $parents;
    }
}
