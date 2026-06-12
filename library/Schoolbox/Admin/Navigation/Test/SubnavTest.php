<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Navigation\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\Navigation\Subnav;

class SubnavTest extends TestCase
{
    public function testIbMenuHiddenWhenFeatureFlagDisabled(): void
    {
        $this->assertFalse(Subnav::isIbVisible(false, true, true));
    }

    public function testIbMenuVisibleForSuperuserWhenFeatureFlagEnabled(): void
    {
        $this->assertTrue(Subnav::isIbVisible(true, true, false));
    }

    public function testIbMenuVisibleForIbCoordinatorWhenFeatureFlagEnabled(): void
    {
        $this->assertTrue(Subnav::isIbVisible(true, false, true));
    }
}
