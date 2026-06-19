<?php

/**
 * Regression tests for enum('true','false') mapper columns.
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

use Schoolbox\RoleMapper;
use Schoolbox\Learning\Quiz\Section\Question\QuestionType\Mapper as QuestionTypeMapper;

class BoolEnumMappingTest extends TestCase
{
    public function testRoleCanChangePasswordUsesBoolEnum(): void
    {
        $mapper = new class extends RoleMapper {
            protected function setupDatabase()
            {
            }
        };
        $hydrator = $mapper->getColumnScalarHydrator('can_change_password');

        $this->assertSame('false', $hydrator->dehydrate(false));
    }

    public function testQuestionTypeFlagsUseBoolEnum(): void
    {
        $mapper = new class extends QuestionTypeMapper {
            protected function setupDatabase()
            {
            }
        };

        $this->assertSame('false', $mapper->getColumnScalarHydrator('has_legend')->dehydrate(false));
        $this->assertSame('false', $mapper->getColumnScalarHydrator('has_config')->dehydrate(false));
    }
}
