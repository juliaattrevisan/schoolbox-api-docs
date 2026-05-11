<?php

/**
 * IB registration session student mapper test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\IB\Registrations\Sessions\Student\Subject\Language\Mapper as SubjectLanguageMapper;

class MapperTest extends TestCase
{
    public function testSubjectLanguageMapperProvidesBatchedLookupMethod(): void
    {
        $this->assertTrue(method_exists(
            SubjectLanguageMapper::class,
            'findByRegistrationSessionStudentIds'
        ));
    }
}
