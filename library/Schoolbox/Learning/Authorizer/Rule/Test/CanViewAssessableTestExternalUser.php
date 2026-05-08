<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule\Test;

/**
 * Stub for \Schoolbox\External\User used in tests that run in a separate process.
 *
 * When CanViewAssessableTest runs in isolation, \Schoolbox\External\User may not
 * yet be autoloaded. This class is aliased to that name via class_alias() to
 * satisfy any runtime checks without pulling in the real external-user integration.
 */
class CanViewAssessableTestExternalUser
{
    /**
     * Returns parents unchanged — the real External\User fetches children from an
     * external system, but in these tests no children need to be resolved.
     */
    public function loadChildren(array $parents): array
    {
        return $parents;
    }
}
