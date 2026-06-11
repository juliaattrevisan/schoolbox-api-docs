<?php

/**
 * @author    Andrew Carlton <andrew.carlton@alaress.com.au>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Schoolbox\Learning\Assessment\Factory;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
use UnexpectedValueException;

class FactoryTest extends TestCase
{
    public function testUnchangedDisallowedWorkTypeCanBeSaved(): void
    {
        $assessment = (new Task())->setWorkType($this->workType(1, false));
        $submittedWorkType = $this->workType(1, false);

        $this->assertSubmittedWorkTypeCanBeSaved($assessment, $submittedWorkType);

        $this->addToAssertionCount(1);
    }

    public function testChangedDisallowedWorkTypeCannotBeSaved(): void
    {
        $assessment = (new Task())->setWorkType($this->workType(1, true));
        $submittedWorkType = $this->workType(2, false);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The selected work type does not support the selected assessment type.');

        $this->assertSubmittedWorkTypeCanBeSaved($assessment, $submittedWorkType);
    }

    private function assertSubmittedWorkTypeCanBeSaved(Task $assessment, WorkType $submittedWorkType): void
    {
        $reflection = new ReflectionClass(Factory::class);
        $method = $reflection->getMethod('assertSubmittedWorkTypeCanBeSaved');
        $method->invoke($reflection->newInstanceWithoutConstructor(), $assessment, $submittedWorkType);
    }

    private function workType(int $id, bool $permittedTask): WorkType
    {
        $workType = new WorkType();
        $workType->setId($id);
        $workType->setPermittedTask($permittedTask);

        return $workType;
    }
}
