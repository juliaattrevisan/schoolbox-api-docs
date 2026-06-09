<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\WorkType\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\WorkType\Mapper;
use Schoolbox\Learning\Assessment\WorkType\Model;

class MapperTest extends TestCase
{
    public function testFindPermittedForAssessmentFiltersByAssessmentTypeColumn(): void
    {
        $expectedWorkTypes = Model::createCollection();

        $mapper = $this
            ->getMockBuilder(Mapper::class)
            ->onlyMethods(['findByColumn'])
            ->getMock();
        $mapper
            ->expects($this->once())
            ->method('findByColumn')
            ->with(
                'permitted_due_work',
                true,
                ['order' => ['sequence' => 'asc']]
            )
            ->willReturn($expectedWorkTypes);

        $this->assertSame(
            $expectedWorkTypes,
            $mapper->findPermittedForAssessment(new DueWork\Model())
        );
    }
}
